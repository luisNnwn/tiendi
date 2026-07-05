<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsInsightsService;
use App\Services\AnalyticsMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsMetricsService $metricsService,
        private readonly AnalyticsInsightsService $insightsService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $supplierId = (int) $request->user()->supplier->id;
        [$from, $to] = $this->resolveRange($request);

        return response()->json($this->metricsService->overview($supplierId, $from, $to));
    }

    public function insights(Request $request): JsonResponse
    {
        $supplierId = (int) $request->user()->supplier->id;
        [$from, $to] = $this->resolveRange($request);

        return response()->json($this->insightsService->generate($supplierId, $from, $to));
    }

    public function refresh(Request $request): JsonResponse
    {
        $supplierId = (int) $request->user()->supplier->id;
        [$from, $to] = $this->resolveRange($request);

        return response()->json([
            'message' => 'Métricas actualizadas correctamente.',
            'overview' => $this->metricsService->overview($supplierId, $from, $to),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (is_string($from) && is_string($to)) {
            try {
                $fromDate = CarbonImmutable::parse($from)->toDateString();
                $toDate = CarbonImmutable::parse($to)->toDateString();

                if ($fromDate <= $toDate) {
                    return [$fromDate, $toDate];
                }
            } catch (\Throwable) {
                // Fallback to current month if parsing fails.
            }
        }

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return [$start, $end];
    }
}

