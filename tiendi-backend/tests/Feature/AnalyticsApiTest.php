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
        'name' => 'Proveedor Analytics',
        'active' => true,
    ]);

    $this->storeA = Store::query()->create([
        'name' => 'Tienda A',
        'phone_number' => '50370000001',
        'active' => true,
    ]);
    $this->storeB = Store::query()->create([
        'name' => 'Tienda B',
        'phone_number' => '50370000002',
        'active' => true,
    ]);

    $this->productA = Product::query()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Producto A',
        'category' => 'Bebidas',
        'unit' => 'caja',
        'price' => 10,
        'active' => true,
    ]);

    $this->productB = Product::query()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Producto B',
        'category' => 'Snacks',
        'unit' => 'paquete',
        'price' => 20,
        'active' => true,
    ]);

    $order = Order::query()->create([
        'store_id' => $this->storeA->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'pending',
        'total' => 40,
        'created_at' => now()->subDays(2),
    ]);

    $order->items()->createMany([
        ['product_id' => $this->productA->id, 'quantity' => 2, 'unit_price' => 10, 'subtotal' => 20],
        ['product_id' => $this->productB->id, 'quantity' => 1, 'unit_price' => 20, 'subtotal' => 20],
    ]);

    $this->token = $this->user->createToken('supplier-api')->plainTextToken;
});

test('analytics overview returns strengthened payload', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/analytics/overview?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

    $response->assertOk()
        ->assertJsonStructure([
            'range' => ['from', 'to'],
            'previous_range' => ['from', 'to'],
            'kpis' => [
                'orders_count',
                'revenue',
                'avg_ticket',
                'clients_current',
                'orders_growth_pct',
                'revenue_growth_pct',
                'catalog_coverage_pct',
                'products_sold',
                'products_active',
                'unsold_stores_count',
                'status_ratio' => [
                    'pending' => ['count', 'pct'],
                    'confirmed' => ['count', 'pct'],
                    'delivered' => ['count', 'pct'],
                ],
            ],
            'top_products',
            'top_stores',
            'sales_by_category',
            'monthly_sales',
            'unsold_stores',
        ]);
});

test('analytics insights fallback returns four recommendations', function () {
    config()->set('services.openai.key', '');

    $response = $this->withToken($this->token)
        ->postJson('/api/analytics/insights?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

    $response->assertOk()
        ->assertJsonPath('source', 'fallback')
        ->assertJsonCount(4, 'insights');
});

