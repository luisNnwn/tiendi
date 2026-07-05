<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbot\StoreChatbotOrderRequest;
use App\Services\ChatbotOrderService;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotOrderService $chatbotOrderService,
    ) {}

    public function store(StoreChatbotOrderRequest $request): JsonResponse
    {
        $message = $request->validated('message');
        $result = $this->chatbotOrderService->handle(
            $request->validated('phone_number'),
            $message,
        );

        return response()->json($result['body'], $result['status']);
    }
}

