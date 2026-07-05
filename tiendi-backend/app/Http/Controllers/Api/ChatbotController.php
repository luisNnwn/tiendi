<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbot\StoreChatbotOrderRequest;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\OrderCreationService;
use App\Services\OrderValidationService;
use App\Services\OpenAiOrderParserService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly OpenAiOrderParserService $openAiOrderParserService,
        private readonly OrderValidationService $orderValidationService,
        private readonly OrderCreationService $orderCreationService,
    ) {}

    public function store(StoreChatbotOrderRequest $request): JsonResponse
    {
        $normalizedPhone = PhoneNumber::normalize($request->validated('phone_number'));
        $message = $request->validated('message');

        if (! PhoneNumber::isValidElSalvador($normalizedPhone)) {
            return response()->json([
                'message' => 'Número inválido. Ingresa un número de El Salvador de 8 dígitos.',
            ], 422);
        }

        $store = Store::query()
            ->where('phone_number', $normalizedPhone)
            ->where('active', true)
            ->first();

        if (! $store) {
            return response()->json([
                'message' => 'Pedido rechazado. La tienda no está registrada o está inactiva.',
            ], 404);
        }

        $suppliers = Supplier::query()
            ->where('active', true)
            ->whereHas('products', fn ($query) => $query->where('active', true))
            ->get();

        if ($suppliers->isEmpty()) {
            return response()->json([
                'message' => 'No hay proveedores activos con productos disponibles.',
            ], 422);
        }

        $keywords = $this->extractKeywords($message);
        $candidates = $this->searchCandidates($suppliers->pluck('id')->all(), $keywords);

        if ($candidates->isEmpty()) {
            return response()->json([
                'message' => 'No encontré productos coincidentes en el catálogo de proveedores.',
            ], 422);
        }

        try {
            $aiResult = $this->openAiOrderParserService->parse($message, $candidates);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        if (! ($aiResult['valid'] ?? false)) {
            return response()->json([
                'message' => 'Pedido inválido según interpretación del asistente.',
                'errors' => $aiResult['errors'] ?? [],
                'candidates' => $this->toCandidatePreview($candidates),
            ], 422);
        }

        $productsById = $candidates->keyBy('id');
        $suppliersById = $suppliers->keyBy('id');
        $itemsBySupplier = [];
        $splitErrors = [];

        foreach (($aiResult['items'] ?? []) as $index => $item) {
            $line = $index + 1;
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $productsById->get($productId);

            if (! $product) {
                $splitErrors[] = "Línea {$line}: producto fuera de candidatos.";

                continue;
            }

            $supplierId = (int) $product->supplier_id;
            $itemsBySupplier[$supplierId] ??= [];
            $itemsBySupplier[$supplierId][] = [
                'product_id' => $productId,
                'quantity' => $item['quantity'] ?? null,
            ];
        }

        if ($splitErrors !== []) {
            return response()->json([
                'message' => 'No se pudo crear el pedido.',
                'errors' => $splitErrors,
            ], 422);
        }

        $createdOrders = collect();
        $validationErrors = [];

        foreach ($itemsBySupplier as $supplierId => $items) {
            /** @var Supplier|null $supplier */
            $supplier = $suppliersById->get((int) $supplierId);

            if (! $supplier) {
                $validationErrors[] = "No hay proveedor activo para los productos del proveedor {$supplierId}.";

                continue;
            }

            $validation = $this->orderValidationService->validate($supplier, $store, $items);

            if (! $validation->valid) {
                $validationErrors = array_merge($validationErrors, $validation->errors);

                continue;
            }

            $createdOrders->push(
                $this->orderCreationService->create(
                    $store,
                    $supplier,
                    $validation->validatedItems,
                    $message,
                )
            );
        }

        if ($validationErrors !== [] || $createdOrders->isEmpty()) {
            return response()->json([
                'message' => 'No se pudo crear el pedido.',
                'errors' => $validationErrors !== [] ? $validationErrors : ['Pedido inválido.'],
            ], 422);
        }

        $firstOrder = $createdOrders->first();

        return response()->json([
            'message' => $createdOrders->count() > 1
                ? 'Pedidos creados correctamente por proveedor.'
                : 'Pedido creado correctamente.',
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'phone_number' => $store->phone_number,
            ],
            'order' => $firstOrder ? new OrderResource($firstOrder) : null,
            'orders' => OrderResource::collection($createdOrders),
            'reply' => $this->buildReply($createdOrders),
        ], 201);
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $message): array
    {
        $text = mb_strtolower($message);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/', trim($text)) ?: [];

        $stopwords = [
            'hola', 'buenas', 'quiero', 'necesito', 'de', 'la', 'el', 'los', 'las', 'y',
            'por', 'favor', 'un', 'una', 'unos', 'unas', 'para', 'me', 'manda', 'mandame',
        ];

        return collect($tokens)
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->reject(fn ($token) => in_array($token, $stopwords, true))
            ->flatMap(function (string $token) {
                $variants = [$token];
                if (mb_strlen($token) > 4 && str_ends_with($token, 's')) {
                    $variants[] = mb_substr($token, 0, -1);
                }

                return $variants;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $supplierIds
     * @param  array<int, string>  $keywords
     * @return Collection<int, Product>
     */
    private function searchCandidates(array $supplierIds, array $keywords): Collection
    {
        $query = Product::query()
            ->whereIn('supplier_id', $supplierIds)
            ->where('active', true);

        if ($keywords !== []) {
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($builder) use ($keywords, $operator) {
                foreach ($keywords as $keyword) {
                    $like = '%'.$keyword.'%';
                    $builder->orWhere('name', $operator, $like)
                        ->orWhere('category', $operator, $like);
                }
            });
        }

        $candidates = $query->orderBy('name')->limit(30)->get();

        if ($candidates->isNotEmpty()) {
            return $candidates;
        }

        return Product::query()
            ->whereIn('supplier_id', $supplierIds)
            ->where('active', true)
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $candidates
     * @return array<int, array{id: int, name: string, unit: string}>
     */
    private function toCandidatePreview(Collection $candidates): array
    {
        return $candidates->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function buildReply(Collection $orders): string
    {
        if ($orders->count() === 1) {
            $order = $orders->first();
            $lines = $order->items
                ->map(fn ($item) => sprintf('- %s x %d %s', $item->product?->name, $item->quantity, $item->product?->unit))
                ->implode("\n");

            return "Pedido recibido correctamente.\n\nResumen:\n{$lines}\n\nEstado: Pendiente de confirmación.";
        }

        $sections = $orders->map(function (Order $order) {
            $supplierName = $order->supplier?->name ?? 'Proveedor';
            $lines = $order->items
                ->map(fn ($item) => sprintf('- %s x %d %s', $item->product?->name, $item->quantity, $item->product?->unit))
                ->implode("\n");

            return "{$supplierName}:\n{$lines}";
        })->implode("\n\n");

        return "Pedidos recibidos correctamente.\n\nResumen por proveedor:\n{$sections}\n\nEstado: Pendiente de confirmación.";
    }
}

