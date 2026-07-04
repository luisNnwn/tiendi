<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class OrderCreationService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, unit_price: string, subtotal: string}>  $validatedItems
     */
    public function create(
        Store $store,
        Supplier $supplier,
        array $validatedItems,
        ?string $rawMessage = null,
        string $status = 'pending',
    ): Order {
        return DB::transaction(function () use ($store, $supplier, $validatedItems, $rawMessage, $status) {
            $total = collect($validatedItems)->sum(fn (array $item) => (float) $item['subtotal']);

            $order = Order::query()->create([
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'status' => $status,
                'raw_message' => $rawMessage,
                'total' => number_format($total, 2, '.', ''),
            ]);

            foreach ($validatedItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $order->load(['store', 'supplier', 'items.product']);
        });
    }
}
