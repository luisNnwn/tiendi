<?php

namespace App\Services;

use App\Data\OrderValidationResult;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class OrderValidationService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int|float|string}>  $items
     */
    public function validate(Supplier $supplier, Store $store, array $items): OrderValidationResult
    {
        $errors = [];

        if (! $store->active) {
            $errors[] = 'La tienda está inactiva.';
        }

        if ($items === []) {
            $errors[] = 'El pedido debe incluir al menos un producto.';
        }

        if ($errors !== []) {
            return OrderValidationResult::invalid(...$errors);
        }

        $productIds = collect($items)->pluck('product_id')->unique()->values();

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $validatedItems = [];

        foreach ($items as $index => $item) {
            $line = $index + 1;
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = $item['quantity'] ?? null;

            if (! $products->has($productId)) {
                $errors[] = "Línea {$line}: producto no encontrado o no pertenece al proveedor.";

                continue;
            }

            $product = $products->get($productId);

            if (! $product->active) {
                $errors[] = "Línea {$line}: el producto \"{$product->name}\" está inactivo.";

                continue;
            }

            if (! is_numeric($quantity) || (float) $quantity <= 0) {
                $errors[] = "Línea {$line}: cantidad inválida para \"{$product->name}\".";

                continue;
            }

            $quantityInt = (int) $quantity;

            if ($quantityInt != $quantity) {
                $errors[] = "Línea {$line}: la cantidad debe ser un número entero.";
            }

            $unitPrice = number_format((float) $product->price, 2, '.', '');
            $subtotal = number_format($quantityInt * (float) $product->price, 2, '.', '');

            $validatedItems[] = [
                'product_id' => $product->id,
                'quantity' => $quantityInt,
                'unit' => $product->unit,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        if ($errors !== []) {
            return OrderValidationResult::invalid(...$errors);
        }

        return OrderValidationResult::success($validatedItems);
    }
}
