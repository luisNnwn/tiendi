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
    config()->set('services.n8n.webhook_secret', 'secret-test');
});

function createWebhookScenario(): Product
{
    $user = User::factory()->create();
    $supplier = Supplier::query()->create([
        'user_id' => $user->id,
        'name' => 'Distribuidora Demo',
        'active' => true,
    ]);

    Store::query()->create([
        'name' => 'Tienda Demo',
        'phone_number' => '50371234567',
        'active' => true,
    ]);

    return Product::query()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Coca Cola 2L',
        'category' => 'Bebidas',
        'unit' => 'caja',
        'price' => 250,
        'active' => true,
    ]);
}

test('n8n webhook rejects unauthorized request', function () {
    $this->postJson('/api/webhooks/n8n/whatsapp-inbound', [
        'phone_number' => '71234567',
        'message' => 'quiero 1 coca',
    ])->assertUnauthorized();
});

test('n8n webhook accepts authorized request and deduplicates by message_id', function () {
    $product = createWebhookScenario();

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'valid' => true,
                        'items' => [[
                            'product_id' => $product->id,
                            'quantity' => 1,
                            'unit' => 'caja',
                        ]],
                        'errors' => [],
                    ], JSON_THROW_ON_ERROR),
                ]],
            ]],
        ]),
    ]);

    $payload = [
        'phone_number' => '71234567',
        'message' => 'quiero 1 coca',
        'message_id' => 'wamid-123',
        'source' => 'n8n-whatsapp',
    ];

    $first = $this->withHeader('X-N8N-Secret', 'secret-test')
        ->postJson('/api/webhooks/n8n/whatsapp-inbound', $payload);

    $first->assertCreated()
        ->assertJsonPath('duplicate', false);

    $second = $this->withHeader('X-N8N-Secret', 'secret-test')
        ->postJson('/api/webhooks/n8n/whatsapp-inbound', $payload);

    $second->assertOk()
        ->assertJsonPath('duplicate', true);
});

