<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nWebhookSecret
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = (string) config('services.n8n.webhook_secret', '');

        if ($configuredSecret === '') {
            return $next($request);
        }

        $providedSecret = (string) $request->header('X-N8N-Secret', '');

        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json([
                'message' => 'Webhook n8n no autorizado.',
            ], 401);
        }

        return $next($request);
    }
}

