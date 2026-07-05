<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Supplier;
use App\Services\DeliveryDateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderStatusRefreshSeeder extends Seeder
{
    public function __construct(
        private readonly DeliveryDateService $deliveryDateService,
    ) {}

    public function run(): void
    {
        $deliveryProfiles = [
            [5],    // Friday
            [2, 5], // Tuesday, Friday
            [1, 4], // Monday, Thursday
            [3, 6], // Wednesday, Saturday
        ];

        $suppliers = Supplier::query()->orderBy('id')->get();

        foreach ($suppliers as $index => $supplier) {
            $supplier->update([
                'delivery_weekdays' => $deliveryProfiles[$index % count($deliveryProfiles)],
                'lead_time_days' => ($index % 3) + 1,
            ]);
        }

        $today = Carbon::today();

        Order::query()->with('supplier')->chunkById(200, function ($orders) use ($today) {
            foreach ($orders as $order) {
                if (! $order->supplier) {
                    continue;
                }

                if (! $order->delivery_date) {
                    $referenceDate = CarbonImmutable::parse($order->created_at)->startOfDay();
                    $order->delivery_date = $this->deliveryDateService->resolveForSupplier(
                        $order->supplier,
                        $referenceDate
                    );
                }

                $delivery = Carbon::parse($order->delivery_date);

                if ($delivery->lt($today->copy()->subDay())) {
                    $order->status = mt_rand(1, 100) <= 82 ? 'delivered' : 'confirmed';
                } elseif ($delivery->lte($today->copy()->addDay())) {
                    $order->status = mt_rand(1, 100) <= 60 ? 'confirmed' : 'pending';
                } else {
                    $order->status = 'pending';
                }

                $order->save();
            }
        });

        $statusCounts = Order::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $this->command?->info('Estado de pedidos actualizado: '.json_encode($statusCounts));
    }
}

