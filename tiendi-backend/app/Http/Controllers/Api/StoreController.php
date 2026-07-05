<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoreController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Store::query()->orderBy('name');

        if ($request->has('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return StoreResource::collection($query->get());
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        $store = Store::query()->create($request->validated());

        return (new StoreResource($store))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Store $store): StoreResource
    {
        return new StoreResource($store);
    }

    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        $store->update($request->validated());

        return new StoreResource($store->fresh());
    }

    public function destroy(Store $store): JsonResponse
    {
        $store->update(['active' => false]);

        return response()->json([
            'message' => 'Tienda desactivada correctamente.',
            'store' => new StoreResource($store->fresh()),
        ]);
    }
}
