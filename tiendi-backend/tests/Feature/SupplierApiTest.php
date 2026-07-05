<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'proveedor@tiendi.com',
        'password' => 'password',
    ]);

    $this->supplier = Supplier::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Distribuidora Demo',
        'active' => true,
    ]);
});

function supplierToken(User $user): string
{
    return $user->createToken('supplier-api')->plainTextToken;
}

test('supplier can login with valid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'proveedor@tiendi.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => ['id', 'name', 'email'],
            'supplier' => ['id', 'name', 'active'],
        ]);
});

test('login rejects invalid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'proveedor@tiendi.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login rejects users without supplier profile', function () {
    User::factory()->create([
        'email' => 'otro@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'otro@example.com',
        'password' => 'password',
    ]);

    $response->assertForbidden()
        ->assertJson(['message' => 'Esta cuenta no está registrada como proveedor.']);
});

test('authenticated supplier can list own products', function () {
    Product::query()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Coca Cola',
        'unit' => 'caja',
        'price' => 250,
        'active' => true,
    ]);

    $response = $this->withToken(supplierToken($this->user))
        ->getJson('/api/products');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Coca Cola');
});

test('supplier can create update and deactivate products', function () {
    $token = supplierToken($this->user);

    $create = $this->withToken($token)->postJson('/api/products', [
        'name' => 'Agua',
        'category' => 'Bebidas',
        'unit' => 'caja',
        'price' => 120,
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.name', 'Agua');

    $productId = $create->json('data.id');

    $this->withToken($token)
        ->putJson("/api/products/{$productId}", [
            'price' => 130,
        ])
        ->assertOk()
        ->assertJsonPath('data.price', '130.00');

    $this->withToken($token)
        ->deleteJson("/api/products/{$productId}")
        ->assertOk()
        ->assertJsonPath('product.active', false);
});

test('supplier cannot access another suppliers product', function () {
    $otherUser = User::factory()->create();
    $otherSupplier = Supplier::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Otro proveedor',
        'active' => true,
    ]);

    $product = Product::query()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Producto ajeno',
        'unit' => 'caja',
        'price' => 99,
        'active' => true,
    ]);

    $this->withToken(supplierToken($this->user))
        ->getJson("/api/products/{$product->id}")
        ->assertNotFound();
});

test('supplier can update delivery settings', function () {
    $token = supplierToken($this->user);

    $this->withToken($token)
        ->putJson('/api/supplier/settings', [
            'delivery_weekdays' => [1, 3, 5],
            'lead_time_days' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.delivery_weekdays.0', 1)
        ->assertJsonPath('data.lead_time_days', 2);
});
