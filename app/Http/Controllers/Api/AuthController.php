<?php

namespace App\Http\Controllers\Api;

use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->active) {
            throw ValidationException::withMessages(['email' => ['Las credenciales no son válidas.']]);
        }

        $token = $user->createToken('samy-boutique-app')->plainTextToken;

        return $this->success(['token' => $token, 'user' => $this->userData($user)], 'Inicio de sesión correcto.');
    }

    public function me(Request $request)
    {
        return $this->success($this->userData($request->user()), 'Usuario autenticado.');
    }

    public function unauthorized()
    {
        return response()->json(['success' => false, 'message' => 'No autenticado.', 'errors' => (object) []], 401);
    }

    public function logout(Request $request)
    {
        $installationId = $request->validate([
            'installation_id' => ['nullable', 'uuid'],
        ])['installation_id'] ?? null;

        if ($installationId) {
            DB::transaction(function () use ($request, $installationId): void {
                $device = NotificationDevice::query()
                    ->where('installation_id', $installationId)
                    ->where('user_id', $request->user()->id)
                    ->lockForUpdate()
                    ->first();
                if (! $device) {
                    return;
                }

                NotificationDelivery::query()
                    ->where('device_id', $device->id)
                    ->whereIn('status', ['pending', 'error_temporal', 'sending'])
                    ->update(['status' => 'cancelled', 'next_attempt_at' => null, 'last_error_code' => 'device_logged_out']);
                $device->update([
                    'user_id' => null,
                    'token' => null,
                    'token_hash' => null,
                    'active' => false,
                    'permission_status' => 'not_determined',
                ]);
            });
        }

        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return $this->success(null, 'Sesión cerrada.');
    }
}
