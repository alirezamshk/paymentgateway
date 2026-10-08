# Payment providers (PSPs)

Per-provider notes: credentials, endpoints, requirements and what has been verified.
Provider-wide, non-secret settings are edited in **Admin → Providers → Configure** (JSON);
merchant credentials are entered per merchant and stored encrypted.

| Provider | Code | Credentials on the merchant | Status |
|----------|------|-----------------------------|--------|
| Sepehr (Bank Saderat) | `sepehr` | `terminal_identifier` | **Live payments verified** (2026-10-08), incl. billing-panel round trip and webhooks |
| ZarinPal | `zarinpal` | `merchant_identifier` (36-char merchant_id) | Sandbox verified (2026-10-08) |
| Sepordeh | `sepordeh` | `merchant_identifier` (merchant key) | Automated tests only |
| Asan Pardakht | `asanpardakht` | `merchant_identifier` (merchantConfigurationId), `username`, `password` | Automated tests only |
| Sandbox | `sandbox` | none | Internal fake PSP, refused in production |

"Automated tests only" means the adapter follows the published API (and, for Sepordeh and
Sepehr, was cross-checked against the open-source `shetabit/multipay` drivers) but has not
completed a real transaction yet. Run one low-value payment before relying on it.

## General requirements

* **Server IP**: Shaparak PSPs usually accept API calls only from IPs registered for your
  terminal. When you move servers, register the new IP first.
* **Callback/return domain**: register the domain of `APP_URL` (e.g. `tech-kala.com`). All
  callbacks come back to the payment service, never to the client sites.
* **Referer**: some PSPs check that the customer arrives from the registered domain. The
  payment page sends `Referrer-Policy: origin` (only `https://<domain>/`, never the payment URL);
  every other page sends no Referer.
* **Customers' VPN**: Shaparak payment pages reject foreign IPs; customers must disable VPNs.
* **Outbound connections**: the server must reach the PSP API host over HTTPS. Check with
  `curl` from the server before enabling a provider.
* **Unverified transactions are reversed** by Shaparak automatically, so a payment that never
  completes verification (e.g. expired) does not keep the customer's money.

## Sepehr (Saderat / Sepehr Electronic Payment)

| Step | Request |
|------|---------|
| Token | `POST https://sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken` (form): `Amount` (Rial), `callbackURL`, `InvoiceID`, `TerminalID`, `Payload` → `{"Status":0,"Accesstoken":"..."}` |
| Pay | Browser `GET https://sepehr.shaparak.ir/Payment/Pay?token=...&terminalid=...` |
| Callback | `POST` to our callback with `respcode` (0 = paid), `respmsg`, `amount`, `invoiceid`, `terminalid`, `tracenumber`, `rrn`, `digitalreceipt`, `cardnumber` (masked), … |
| Verify | `POST https://sepehr.shaparak.ir/Rest/V1/PeymentApi/Advice` (form): `digitalreceipt`, `Tid` → `{"Status":"Ok"|"Duplicate"|"NOk","ReturnId":<amount>}` |

Lessons from going live:

* The old `https://sepehr.shaparak.ir:8081/V1/...` API and the `:8080/Pay` page **refuse
  connections**. Use `/Rest/V1/...` on port 443 and `/Payment/Pay` (the adapter defaults).
* `Status: -2` = the server IP is not registered for the terminal (or the terminal id is wrong).
* `UrlReferrer` error on Sepehr's page = the browser sent no Referer from the registered domain.
  Fixed by the payment page's `Referrer-Policy: origin`.
* The token response key is spelled `Accesstoken`; keys are read case-insensitively.
* `ReturnId` from Advice must equal the amount in Rials, otherwise the payment is marked failed
  with `AMOUNT_MISMATCH` for manual review.
* `rrn` becomes `reference_number`, `tracenumber` becomes `trace_number`.

Settings (all optional): `api_url` (default `https://sepehr.shaparak.ir/Rest`), `pay_url`,
`pay_method` (`GET`|`POST`).

Error codes: `-1` transaction not found, `-2` IP mismatch, `-3` general PSP error, `-4` request
not allowed for this transaction, `-5` invalid IP, `-6` reversal not enabled. Callback
`respcode` `-1` = cancelled by customer, `-2` = timed out.

Probe from the server (no money moves, terminal `0`):

```bash
curl -sS -m 15 -X POST -d "Amount=10000&callbackURL=https://example.com/&InvoiceID=1&TerminalID=0&Payload=" \
  https://sepehr.shaparak.ir/Rest/V1/PeymentApi/GetToken      # expect {"Status":-2,"Accesstoken":""}
```

## ZarinPal (REST v4)

* Request `POST {base}/pg/v4/payment/request.json`, pay `GET {base}/pg/StartPay/{authority}`,
  callback `?Authority=...&Status=OK|NOK`, verify `POST {base}/pg/v4/payment/verify.json`
  (`code` 100 = verified, 101 = already verified).
* `{base}` is `https://sandbox.zarinpal.com` when the provider config has `{"sandbox": true}`
  (seeded default), otherwise `https://payment.zarinpal.com`. Set `{"sandbox": false}` for live.
* The sandbox accepts any well-formed 36-character merchant_id (generate one with
  `cat /proc/sys/kernel/random/uuid`).
* ZarinPal accepts IRR and IRT, so the payment currency is passed through unchanged.

## Sepordeh

* Add invoice `POST https://sepordeh.com/merchant/invoices/add` (form): `merchant`, `amount`
  (**Toman**), `callback`, `orderId`, `description` → `information.invoice_id`.
* Pay `GET .../merchant/invoices/pay/id:{invoice_id}`, or with `{"direct": true}`
  `.../pay/automatic:true/id:{invoice_id}` (skips Sepordeh's own page).
* Callback `?authority={invoice_id}`; verify `POST .../merchant/invoices/verify` (form):
  `merchant`, `authority` → `status` 200 + `information.card`.
* IRR payments are converted to Tomans exactly; an IRR amount not divisible by 10 fails with
  `AMOUNT_NOT_CONVERTIBLE`. Settings: `base_url`, `amount_currency` (default `IRT`), `direct`.

## Asan Pardakht (IPG REST v1)

* Headers `usr` / `pwd`. Token `POST /v1/Token`, pay `POST https://asan.shaparak.ir` with
  `RefId`, result `GET /v1/TranResult`, verify `POST /v1/Verify`, **settlement
  `POST /v1/Settlement`** (required; retried by `payments:reconcile` if it fails).
* Amounts in Rials. Settings: `base_url`, `pay_url`.

## Adding a provider

See [ARCHITECTURE.md → Adding a PSP](ARCHITECTURE.md#adding-a-psp).
