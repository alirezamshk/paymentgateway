<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Client;
use App\Models\Merchant;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Clients manage their own merchants. All queries are scoped to the authenticated client.
 */
class MerchantController extends Controller
{
    public function __construct(private readonly MerchantService $merchants) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return MerchantResource::collection($this->client($request)->merchants()->with('provider')->orderBy('id')->get());
    }

    public function show(Request $request, string $merchantId): MerchantResource
    {
        return new MerchantResource($this->find($request, $merchantId));
    }

    public function store(MerchantRequest $request): JsonResponse
    {
        $client = $this->client($request);
        $merchant = $this->merchants->create($client, $request->validated(), 'client', $client->id);

        return (new MerchantResource($merchant->load('provider')))->response()->setStatusCode(201);
    }

    public function update(MerchantRequest $request, string $merchantId): MerchantResource
    {
        $client = $this->client($request);
        $merchant = $this->merchants->update($this->find($request, $merchantId), $request->validated(), 'client', $client->id);

        return new MerchantResource($merchant->load('provider'));
    }

    private function client(Request $request): Client
    {
        return $request->attributes->get('client');
    }

    private function find(Request $request, string $merchantId): Merchant
    {
        $merchant = $this->client($request)->merchants()->with('provider')->where('public_id', $merchantId)->first();

        if ($merchant === null) {
            throw new ApiException('MERCHANT_NOT_FOUND', 'Merchant not found.', 404);
        }

        return $merchant;
    }
}
