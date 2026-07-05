<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Supplier */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'delivery_weekdays' => $this->delivery_weekdays ?? [],
            'lead_time_days' => (int) ($this->lead_time_days ?? 2),
            'active' => $this->active,
        ];
    }
}
