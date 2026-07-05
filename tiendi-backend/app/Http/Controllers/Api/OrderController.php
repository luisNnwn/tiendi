<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Store;
use App\Services\OrderCreationService;
use App\Services\OrderValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderValidationService $orderValidationService,
        private readonly OrderCreationService $orderCreationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $supplier = $request->user()->supplier;

        $query = Order::query()
            ->where('supplier_id', $supplier->id)
            ->with(['store', 'items.product'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return OrderResource::collection($query->get());
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        $store = Store::query()->findOrFail($request->validated('store_id'));

        $validation = $this->orderValidationService->validate(
            $supplier,
            $store,
            $request->validated('items'),
        );

        if (! $validation->valid) {
            return response()->json([
                'message' => 'No se pudo crear el pedido.',
                'errors' => $validation->errors,
            ], 422);
        }

        $order = $this->orderCreationService->create(
            $store,
            $supplier,
            $validation->validatedItems,
            $request->validated('raw_message'),
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->ensureOwnsOrder($request, $order);

        $order->load(['store', 'items.product']);

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $this->ensureOwnsOrder($request, $order);

        $order->update([
            'status' => $request->validated('status'),
        ]);

        return new OrderResource($order->fresh()->load(['store', 'items.product']));
    }

    private function ensureOwnsOrder(Request $request, Order $order): void
    {
        if ($order->supplier_id !== $request->user()->supplier->id) {
            abort(404);
        }
    }
}
