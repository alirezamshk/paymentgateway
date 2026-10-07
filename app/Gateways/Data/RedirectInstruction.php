<?php

namespace App\Gateways\Data;

/** How the payment page sends the customer to the PSP. */
final class RedirectInstruction
{
    /**
     * @param  array<string, scalar>  $fields  form fields for POST redirects
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method = 'GET',
        public readonly array $fields = [],
    ) {}

    /** @return array{url: string, method: string, fields: array<string, scalar>} */
    public function toArray(): array
    {
        return ['url' => $this->url, 'method' => $this->method, 'fields' => $this->fields];
    }

    /** @param array{url: string, method?: string, fields?: array<string, scalar>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['url'], $data['method'] ?? 'GET', $data['fields'] ?? []);
    }
}
