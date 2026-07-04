<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
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

    $this->supplier->stores()->attach($this->store->id, ['active' => true]);

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

test('order creation rejects unlinked store', function () {
    $otherStore = Store::query()->create([
        'name' => 'Otra tienda',
        'phone_number' => '50378889999',
        'active' => true,
    ]);

    $this->withToken($this->token)
        ->postJson('/api/orders', [
            'store_id' => $otherStore->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No se pudo crear el pedido.');
});

test('supplier can list and view own orders', function () {
    $order = Order::query()->create([
        'store_id' => $this->store->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'pending',
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
        'total' => 100,
    ]);

    $this->withToken($this->token)
        ->getJson("/api/orders/{$order->id}")
        ->assertNotFound();
});
