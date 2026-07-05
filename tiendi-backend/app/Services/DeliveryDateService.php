<?php

namespace App\Services;

use App\Models\Supplier;
use Carbon\CarbonImmutable;
use RuntimeException;

class DeliveryDateService
{
    public const DEFAULT_WEEKDAYS = [5]; // Friday
    public const DEFAULT_LEAD_TIME_DAYS = 2;

    public function resolveForSupplier(Supplier $supplier, ?CarbonImmutable $referenceDate = null): string
    {
        $base = ($referenceDate ?? CarbonImmutable::now())->startOfDay();
        $weekdays = $this->sanitizeWeekdays($supplier->delivery_weekdays);
        $leadTimeDays = $this->sanitizeLeadTime($supplier->lead_time_days);

        for ($weekOffset = 0; $weekOffset <= 8; $weekOffset++) {
            foreach ($weekdays as $weekday) {
                $candidate = $this->nextOrSame($base->addWeeks($weekOffset), $weekday);
                $anticipationDays = $base->diffInDays($candidate, false);

                if ($anticipationDays >= $leadTimeDays) {
                    return $candidate->toDateString();
                }
            }
        }

        throw new RuntimeException('No se pudo calcular una fecha de entrega válida.');
    }

    /**
     * @param  mixed  $weekdays
     * @return array<int, int>
     */
    private function sanitizeWeekdays(mixed $weekdays): array
    {
        $values = is_array($weekdays) ? $weekdays : self::DEFAULT_WEEKDAYS;

        return collect($values)
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all() ?: self::DEFAULT_WEEKDAYS;
    }

    private function sanitizeLeadTime(mixed $leadTimeDays): int
    {
        $value = (int) $leadTimeDays;

        return max(0, min($value, 30));
    }

    private function nextOrSame(CarbonImmutable $date, int $weekday): CarbonImmutable
    {
        $delta = ($weekday - $date->dayOfWeek + 7) % 7;

        return $date->addDays($delta);
    }
}

