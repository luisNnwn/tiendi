<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\StoreN8nWhatsappInboundRequest;
use App\Services\ChatbotOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class N8nWebhookController extends Controller
{
    public function __construct(
        private readonly ChatbotOrderService $chatbotOrderService,
    ) {}

    public function whatsappInbound(StoreN8nWhatsappInboundRequest $request): JsonResponse
    {
        $messageId = $request->validated('message_id');
        $cacheKey = $messageId ? "n8n:whatsapp:inbound:{$messageId}" : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()->json([
                ...$cached,
                'duplicate' => true,
            ], 200);
        }

        $result = $this->chatbotOrderService->handle(
            $request->validated('phone_number'),
            $request->validated('message'),
        );

        $body = [
            ...$result['body'],
            'duplicate' => false,
            'meta' => [
                'message_id' => $messageId,
                'session_id' => $request->validated('session_id'),
                'source' => $request->validated('source', 'n8n'),
            ],
        ];

        if ($cacheKey && $result['status'] < 500) {
            Cache::put($cacheKey, $body, now()->addHours(6));
        }

        return response()->json($body, $result['status']);
    }
}

