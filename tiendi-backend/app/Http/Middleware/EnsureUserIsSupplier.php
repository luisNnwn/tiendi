<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupplier
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->supplier) {
            return response()->json([
                'message' => 'Acceso restringido a proveedores.',
            ], 403);
        }

        if (! $user->supplier->active) {
            return response()->json([
                'message' => 'La cuenta del proveedor está inactiva.',
            ], 403);
        }

        return $next($request);
    }
}
