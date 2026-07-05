<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.openai.key', 'test-openai-key');
    config()->set('services.openai.model', 'gpt-4.1-mini');

    $this->user = User::factory()->create();
    $this->supplier = Supplier::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Distribuidora Demo',
        'active' => true,
    ]);

    $this->store = Store::query()->create([
        'name' => 'Tienda Demo',
        'phone_number' => '50371234567',
        'active' => true,
    ]);

    $this->coca = Product::query()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Coca Cola 2L',
        'category' => 'Bebidas',
        'unit' => 'caja',
        'price' => 250,
        'active' => true,
    ]);
});

test('chatbot endpoint creates order from openai response', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [
                [
                    'content' => [
                        [
                            'text' => json_encode([
                                'valid' => true,
                                'items' => [
                                    [
                                        'product_id' => $this->coca->id,
                                        'quantity' => 2,
                                        'unit' => 'caja',
                                    ],
                                ],
                                'errors' => [],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->postJson('/api/chatbot/test-order', [
        'phone_number' => '7123-4567',
        'message' => 'quiero 2 cajas de coca',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Pedido creado correctamente.')
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonPath('order.total', '500.00');

    $this->assertDatabaseHas('orders', [
        'store_id' => $this->store->id,
        'supplier_id' => $this->supplier->id,
        'total' => 500,
    ]);
});

test('chatbot endpoint rejects unknown store', function () {
    $this->postJson('/api/chatbot/test-order', [
        'phone_number' => '7999-9999',
        'message' => 'quiero 1 caja de coca',
    ])->assertNotFound();
});

test('chatbot endpoint returns ai validation errors', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [
                [
                    'content' => [
                        [
                            'text' => json_encode([
                                'valid' => false,
                                'items' => [],
                                'errors' => ['No se indicó la cantidad.'],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->postJson('/api/chatbot/test-order', [
        'phone_number' => '7123-4567',
        'message' => 'quiero coca',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.0', 'No se indicó la cantidad.');
});

test('chatbot endpoint splits order by multiple suppliers', function () {
    $otherUser = User::factory()->create();
    $otherSupplier = Supplier::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Snacks SV',
        'active' => true,
    ]);

    $oreo = Product::query()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Galletas Oreo',
        'category' => 'Snacks',
        'unit' => 'paquete',
        'price' => 100,
        'active' => true,
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [
                [
                    'content' => [
                        [
                            'text' => json_encode([
                                'valid' => true,
                                'items' => [
                                    [
                                        'product_id' => $this->coca->id,
                                        'quantity' => 2,
                                        'unit' => 'caja',
                                    ],
                                    [
                                        'product_id' => $oreo->id,
                                        'quantity' => 3,
                                        'unit' => 'paquete',
                                    ],
                                ],
                                'errors' => [],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->postJson('/api/chatbot/test-order', [
        'phone_number' => '7123-4567',
        'message' => 'quiero 2 cajas de coca y 3 oreos',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Pedidos creados correctamente por proveedor.')
        ->assertJsonCount(2, 'orders');

    $this->assertDatabaseCount('orders', 2);
});

