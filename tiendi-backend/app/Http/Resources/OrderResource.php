<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'supplier_id' => $this->supplier_id,
            'status' => $this->status,
            'delivery_date' => $this->delivery_date?->toDateString(),
            'raw_message' => $this->raw_message,
            'total' => $this->total,
            'store' => new StoreResource($this->whenLoaded('store')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
