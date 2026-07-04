<?php

use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSupplierUser(): array
{
    $user = User::factory()->create(['password' => 'password']);
    $supplier = Supplier::query()->create([
        'user_id' => $user->id,
        'name' => 'Distribuidora Demo',
        'active' => true,
    ]);

    return [$user, $supplier];
}

test('supplier can register store with local el salvador phone number', function () {
    [$user, $supplier] = createSupplierUser();
    $token = $user->createToken('supplier-api')->plainTextToken;

    $create = $this->withToken($token)->postJson('/api/stores', [
        'name' => 'Abarrotes La Esquina',
        'phone_number' => '7123-4567',
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.name', 'Abarrotes La Esquina')
        ->assertJsonPath('data.phone_number', '50371234567')
        ->assertJsonPath('data.phone_display', '+503 7123-4567');

    $this->assertDatabaseHas('stores', [
        'name' => 'Abarrotes La Esquina',
        'phone_number' => '50371234567',
    ]);

    $this->assertDatabaseHas('store_supplier', [
        'supplier_id' => $supplier->id,
        'store_id' => $create->json('data.id'),
    ]);
});

test('supplier can update and deactivate stores', function () {
    [$user, $supplier] = createSupplierUser();
    $token = $user->createToken('supplier-api')->plainTextToken;

    $store = Store::query()->create([
        'name' => 'Mini Super',
        'phone_number' => '50377778888',
        'active' => true,
    ]);

    $supplier->stores()->attach($store->id, ['active' => true]);

    $this->withToken($token)
        ->putJson("/api/stores/{$store->id}", [
            'name' => 'Mini Super Centro',
            'phone_number' => '7888-9999',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Mini Super Centro')
        ->assertJsonPath('data.phone_number', '50378889999');

    $this->withToken($token)
        ->deleteJson("/api/stores/{$store->id}")
        ->assertOk()
        ->assertJsonPath('store.active', false);
});

test('store registration rejects duplicate phone numbers', function () {
    [$user] = createSupplierUser();

    Store::query()->create([
        'name' => 'Tienda existente',
        'phone_number' => '50371234567',
        'active' => true,
    ]);

    $token = $user->createToken('supplier-api')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/stores', [
            'name' => 'Otra tienda',
            'phone_number' => '71234567',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});

test('store registration rejects invalid el salvador phone numbers', function () {
    [$user] = createSupplierUser();
    $token = $user->createToken('supplier-api')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/stores', [
            'name' => 'Tienda inválida',
            'phone_number' => '12345',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});
