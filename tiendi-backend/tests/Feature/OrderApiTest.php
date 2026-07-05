<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'password']);
    $this->supplier = Supplier::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Distribuidora Demo',
        'active' => true,
    ]);

    $this->store = Store::query()->create([
        'name' => 'Abarrotes La Esquina',
        'phone_number' => '50371234567',
        'active' => true,
    ]);

    $this->product = Product::query()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Coca Cola 2L',
        'unit' => 'caja',
        'price' => 250,
        'active' => true,
    ]);

    $this->token = $this->user->createToken('supplier-api')->plainTextToken;
});

test('supplier can create an order', function () {
    $response = $this->withToken($this->token)->postJson('/api/orders', [
        'store_id' => $this->store->id,
        'raw_message' => '2 cajas de coca',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.delivery_date', now()->next('friday')->toDateString())
        ->assertJsonPath('data.total', '500.00')
        ->assertJsonCount(1, 'data.items');

    $this->assertDatabaseHas('orders', [
        'store_id' => $this->store->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'pending',
        'total' => 500,
    ]);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 250,
        'subtotal' => 500,
    ]);
});

test('order creation allows active stores from global registry', function () {
    $otherStore = Store::query()->create([
        'name' => 'Otra tienda',
        'phone_number' => '50378889999',
        'active' => true,
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/orders', [
            'store_id' => $otherStore->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated();

    expect($response->json('data.store.id'))->toBe($otherStore->id);
});

test('supplier can list and view own orders', function () {
    $order = Order::query()->create([
        'store_id' => $this->store->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'pending',
        'delivery_date' => now()->next('friday')->toDateString(),
        'total' => 250,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 250,
        'subtotal' => 250,
    ]);

    $this->withToken($this->token)
        ->getJson('/api/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id);

    $this->withToken($this->token)
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.store.name', 'Abarrotes La Esquina');
});

test('supplier cannot view another suppliers order', function () {
    $otherUser = User::factory()->create();
    $otherSupplier = Supplier::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Otro proveedor',
        'active' => true,
    ]);

    $order = Order::query()->create([
        'store_id' => $this->store->id,
        'supplier_id' => $otherSupplier->id,
        'status' => 'pending',
        'delivery_date' => now()->next('friday')->toDateString(),
        'total' => 100,
    ]);

    $this->withToken($this->token)
        ->getJson("/api/orders/{$order->id}")
        ->assertNotFound();
});

test('new items append to existing pending order with same delivery date', function () {
    $first = $this->withToken($this->token)->postJson('/api/orders', [
        'store_id' => $this->store->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    $second = $this->withToken($this->token)->postJson('/api/orders', [
        'store_id' => $this->store->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    expect($first->json('data.id'))->toBe($second->json('data.id'));
    expect((int) $second->json('data.items.0.quantity'))->toBe(3);
    $this->assertDatabaseCount('orders', 1);
});

test('supplier can update order status to delivered', function () {
    $order = Order::query()->create([
        'store_id' => $this->store->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'pending',
        'delivery_date' => now()->next('friday')->toDateString(),
        'total' => 120,
    ]);

    $this->withToken($this->token)
        ->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'delivered',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered');
});

test('thursday orders move to next week friday when lead time is 2 days', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-02 10:00:00')); // Thursday

    try {
        $this->supplier->update([
            'delivery_weekdays' => [5], // Friday
            'lead_time_days' => 2,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/orders', [
            'store_id' => $this->store->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.delivery_date', '2026-07-10');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('new order is created when pending exists for different delivery date', function () {
    Order::query()->create([
        'store_id' => $this->store->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'pending',
        'delivery_date' => now()->addWeek()->next('friday')->toDateString(),
        'total' => 10,
    ]);

    $this->withToken($this->token)->postJson('/api/orders', [
        'store_id' => $this->store->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    $this->assertDatabaseCount('orders', 2);
});
