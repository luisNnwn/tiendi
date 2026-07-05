<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DeliveryDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function signup(SignupRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $user = DB::transaction(function () use ($payload) {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => $payload['password'],
            ]);

            Supplier::query()->create([
                'user_id' => $user->id,
                'name' => $payload['supplier_name'],
                'phone_number' => $payload['phone_number'],
                'delivery_weekdays' => DeliveryDateService::DEFAULT_WEEKDAYS,
                'lead_time_days' => DeliveryDateService::DEFAULT_LEAD_TIME_DAYS,
                'active' => true,
            ]);

            return $user->load('supplier');
        });

        $token = $user->createToken('supplier-api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'supplier' => new SupplierResource($user->supplier),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->with('supplier')
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son válidas.'],
            ]);
        }

        if (! $user->supplier) {
            return response()->json([
                'message' => 'Esta cuenta no está registrada como proveedor.',
            ], 403);
        }

        if (! $user->supplier->active) {
            return response()->json([
                'message' => 'La cuenta del proveedor está inactiva.',
            ], 403);
        }

        $user->tokens()->where('name', 'supplier-api')->delete();

        $token = $user->createToken('supplier-api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'supplier' => new SupplierResource($user->supplier),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('supplier');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'supplier' => new SupplierResource($user->supplier),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
