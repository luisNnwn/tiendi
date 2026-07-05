<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\UpdateDeliverySettingsRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\Request;

class SupplierSettingsController extends Controller
{
    public function show(Request $request): SupplierResource
    {
        return new SupplierResource($request->user()->supplier);
    }

    public function update(UpdateDeliverySettingsRequest $request): SupplierResource
    {
        $supplier = $request->user()->supplier;

        $supplier->update([
            'delivery_weekdays' => collect($request->validated('delivery_weekdays'))
                ->map(fn ($day) => (int) $day)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'lead_time_days' => (int) $request->validated('lead_time_days'),
        ]);

        return new SupplierResource($supplier->fresh());
    }
}

