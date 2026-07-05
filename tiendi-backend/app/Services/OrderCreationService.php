<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OrderCreationService
{
    public function __construct(
        private readonly DeliveryDateService $deliveryDateService,
    ) {}

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
            $deliveryDate = $this->deliveryDateService->resolveForSupplier($supplier, CarbonImmutable::now());
            $total = collect($validatedItems)->sum(fn (array $item) => (float) $item['subtotal']);

            $order = null;
            if ($status === 'pending') {
                $order = Order::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('store_id', $store->id)
                    ->where('status', 'pending')
                    ->whereDate('delivery_date', $deliveryDate)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $order) {
                $order = Order::query()->create([
                    'store_id' => $store->id,
                    'supplier_id' => $supplier->id,
                    'status' => $status,
                    'delivery_date' => $deliveryDate,
                    'raw_message' => $rawMessage,
                    'total' => '0.00',
                ]);
            } elseif ($rawMessage) {
                $order->update([
                    'raw_message' => trim(($order->raw_message ? $order->raw_message."\n" : '').$rawMessage),
                ]);
            }

            foreach ($validatedItems as $item) {
                /** @var OrderItem|null $existingItem */
                $existingItem = $order->items()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existingItem) {
                    $newQuantity = $existingItem->quantity + $item['quantity'];
                    $newSubtotal = number_format($newQuantity * (float) $item['unit_price'], 2, '.', '');

                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $newSubtotal,
                    ]);
                } else {
                    $order->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }
            }

            $orderTotal = (float) $order->items()->sum('subtotal');
            $order->update([
                'total' => number_format($orderTotal, 2, '.', ''),
            ]);

            return $order->load(['store', 'supplier', 'items.product']);
        });
    }
}
