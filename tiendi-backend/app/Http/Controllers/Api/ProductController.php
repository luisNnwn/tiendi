<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $supplier = $request->user()->supplier;

        $query = Product::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('name');

        if ($request->has('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return ProductResource::collection($query->get());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        $product = $supplier->products()->create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->ensureOwnsProduct($request, $product);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->ensureOwnsProduct($request, $product);

        $product->update($request->validated());

        return new ProductResource($product->fresh());
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->ensureOwnsProduct($request, $product);

        $product->update(['active' => false]);

        return response()->json([
            'message' => 'Producto desactivado correctamente.',
            'product' => new ProductResource($product->fresh()),
        ]);
    }

    private function ensureOwnsProduct(Request $request, Product $product): void
    {
        if ($product->supplier_id !== $request->user()->supplier->id) {
            abort(404);
        }
    }
}
