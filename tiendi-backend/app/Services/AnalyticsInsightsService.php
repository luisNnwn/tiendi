<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AnalyticsInsightsService
{
    public function __construct(
        private readonly AnalyticsMetricsService $metricsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(int $supplierId, string $fromDate, string $toDate): array
    {
        $overview = $this->metricsService->overview($supplierId, $fromDate, $toDate);

        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            return [
                'insights' => $this->fallbackInsights($overview),
                'source' => 'fallback',
            ];
        }

        $model = (string) config('services.openai.model', 'gpt-4.1-mini');
        $response = Http::withToken($apiKey)
            ->timeout(40)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' => 'Genera exactamente 4 recomendaciones accionables para el proveedor en español. Deben ser concretas y priorizadas según impacto comercial.',
                'input' => json_encode($overview, JSON_UNESCAPED_UNICODE),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'supplier_insights',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'insights' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => ['insights'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return [
                'insights' => $this->fallbackInsights($overview),
                'source' => 'fallback',
            ];
        }

        $payload = $response->json();
        $text = $this->extractOutputText(is_array($payload) ? $payload : []);
        if (! is_string($text) || $text === '') {
            return [
                'insights' => $this->fallbackInsights($overview),
                'source' => 'fallback',
            ];
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded) || ! is_array($decoded['insights'] ?? null)) {
            return [
                'insights' => $this->fallbackInsights($overview),
                'source' => 'fallback',
            ];
        }

        return [
            'insights' => array_values($decoded['insights']),
            'source' => 'openai',
        ];
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return array<int, string>
     */
    private function fallbackInsights(array $overview): array
    {
        $kpis = $overview['kpis'] ?? [];
        $clientsCurrent = $kpis['clients_current'] ?? '0/0';
        $avgTicket = $kpis['avg_ticket'] ?? '0.00';
        $unsoldCount = count($overview['unsold_stores'] ?? []);
        $revenueGrowth = $kpis['revenue_growth_pct'] ?? 0;
        $catalogCoverage = $kpis['catalog_coverage_pct'] ?? 0;

        return [
            "Clientes actuales en el periodo: {$clientsCurrent}. Prioriza reactivación en tiendas sin compra.",
            "Ticket promedio actual: $ {$avgTicket}. Ofrece combos para incrementarlo en próximos pedidos.",
            "Tienes {$unsoldCount} tiendas sin venta en el rango seleccionado. Planifica seguimiento comercial semanal.",
            "Crecimiento de ingresos vs periodo previo: {$revenueGrowth}% y cobertura de catálogo: {$catalogCoverage}%. Refuerza rotación en categorías con baja participación.",
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOutputText(array $payload): ?string
    {
        $fallback = $payload['output_text'] ?? null;
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        $output = $payload['output'] ?? null;
        if (! is_array($output)) {
            return null;
        }

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

        return null;
    }
}

