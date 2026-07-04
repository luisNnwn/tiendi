<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiOrderParserService
{
    private const ORDER_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'valid' => ['type' => 'boolean'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'quantity' => ['type' => 'number'],
                        'unit' => ['type' => 'string'],
                    ],
                    'required' => ['product_id', 'quantity', 'unit'],
                    'additionalProperties' => false,
                ],
            ],
            'errors' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['valid', 'items', 'errors'],
        'additionalProperties' => false,
    ];

    private const INSTRUCTIONS = <<<'TXT'
Eres un agente que convierte mensajes de WhatsApp en pedidos JSON.
Solo puedes usar productos del catálogo enviado.
Si falta cantidad, producto o hay ambigüedad, marca el pedido como inválido.
TXT;

    /**
     * @param  Collection<int, \App\Models\Product>  $candidates
     * @return array{valid: bool, items: array<int, array{product_id: int, quantity: int|float, unit: string}>, errors: array<int, string>}
     */
    public function parse(string $message, Collection $candidates): array
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }

        $model = (string) config('services.openai.model', 'gpt-4.1-mini');

        $response = Http::withToken($apiKey)
            ->timeout(40)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' => self::INSTRUCTIONS,
                'input' => $this->buildInput($message, $candidates),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'whatsapp_order',
                        'strict' => true,
                        'schema' => self::ORDER_SCHEMA,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo procesar el mensaje con OpenAI.');
        }

        $payload = $response->json();
        $text = $this->extractOutputText($payload);
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI devolvió una respuesta inválida.');
        }

        return [
            'valid' => (bool) ($decoded['valid'] ?? false),
            'items' => is_array($decoded['items'] ?? null) ? $decoded['items'] : [],
            'errors' => is_array($decoded['errors'] ?? null) ? array_values($decoded['errors']) : [],
        ];
    }

    /**
     * @param  Collection<int, \App\Models\Product>  $candidates
     */
    private function buildInput(string $message, Collection $candidates): string
    {
        $catalogLines = $candidates
            ->map(fn ($product) => sprintf(
                'ID: %d | %s | unidad: %s | aliases: %s',
                $product->id,
                $product->name,
                $product->unit,
                $this->buildAliases($product->name, $product->category)
            ))
            ->implode("\n");

        return "Productos disponibles:\n{$catalogLines}\n\nMensaje de la tienda:\n\"{$message}\"";
    }

    private function buildAliases(string $name, ?string $category): string
    {
        $tokens = preg_split('/\s+/', mb_strtolower($name)) ?: [];
        $aliases = collect($tokens)
            ->filter(fn ($token) => mb_strlen($token) >= 4)
            ->take(2)
            ->values();

        if ($category) {
            $aliases->push(mb_strtolower($category));
        }

        $aliasString = $aliases->unique()->implode(', ');

        return $aliasString !== '' ? $aliasString : '-';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOutputText(array $payload): string
    {
        $output = $payload['output'] ?? null;

        if (is_array($output)) {
            foreach ($output as $chunk) {
                $content = $chunk['content'] ?? null;
                if (! is_array($content)) {
                    continue;
                }

                foreach ($content as $part) {
                    $text = $part['text'] ?? null;
                    if (is_string($text) && $text !== '') {
                        return $text;
                    }
                }
            }
        }

        $fallback = $payload['output_text'] ?? null;
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        throw new RuntimeException('OpenAI no devolvió contenido parseable.');
    }
}

