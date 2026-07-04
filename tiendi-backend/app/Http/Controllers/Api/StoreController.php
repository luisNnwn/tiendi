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
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $supplier = $request->user()->supplier;

        $query = $supplier->stores()->orderBy('name');

        if ($request->has('active')) {
            $query->where('stores.active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return StoreResource::collection($query->get());
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        $store = DB::transaction(function () use ($request, $supplier) {
            $store = Store::query()->create($request->validated());

            $supplier->stores()->attach($store->id, ['active' => true]);

            return $store;
        });

        return (new StoreResource($store))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Store $store): StoreResource
    {
        $this->ensureLinkedToSupplier($request, $store);

        return new StoreResource($store);
    }

    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        $this->ensureLinkedToSupplier($request, $store);

        $store->update($request->validated());

        return new StoreResource($store->fresh());
    }

    public function destroy(Request $request, Store $store): JsonResponse
    {
        $this->ensureLinkedToSupplier($request, $store);

        $store->update(['active' => false]);

        return response()->json([
            'message' => 'Tienda desactivada correctamente.',
            'store' => new StoreResource($store->fresh()),
        ]);
    }

    private function ensureLinkedToSupplier(Request $request, Store $store): void
    {
        $supplier = $request->user()->supplier;

        $isLinked = $supplier->stores()
            ->whereKey($store->id)
            ->exists();

        if (! $isLinked) {
            abort(404);
        }
    }
}
