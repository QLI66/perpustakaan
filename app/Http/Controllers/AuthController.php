<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponder;

    /**
     * Authenticate user and issue Sanctum token
     */
    public function login(Request $request)
    {
        $loginField = $request->has('login') ? 'login' : 'email';

        $request->validate([
            $loginField => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $loginValue = trim((string) ($request->input('login') ?? $request->input('email')));

        $user = User::where(function ($query) use ($loginValue) {
            $query->where('username', $loginValue)
                ->orWhere(function ($q) use ($loginValue) {
                    $q->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->where('email', $loginValue);
                })
                ->orWhereHas('member', function ($mq) use ($loginValue) {
                    $mq->where('member_number', $loginValue)
                        ->orWhere('identity_number', $loginValue);
                });
        })->first();

        if (! $user) {
            $user = User::where(function ($query) use ($loginValue) {
                $query->whereRaw('LOWER(username) = LOWER(?)', [$loginValue])
                    ->orWhere(function ($q) use ($loginValue) {
                        $q->whereNotNull('email')
                            ->where('email', '!=', '')
                            ->whereRaw('LOWER(email) = LOWER(?)', [$loginValue]);
                    })
                    ->orWhereHas('member', function ($mq) use ($loginValue) {
                        $mq->whereRaw('LOWER(member_number) = LOWER(?)', [$loginValue])
                            ->orWhereRaw('LOWER(identity_number) = LOWER(?)', [$loginValue]);
                    });
            })->first();
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                $loginField => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        $deviceName = $request->device_name ?? 'auth_token';

        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'Berhasil login');
    }

    /**
     * Get authenticated user info (requires auth:sanctum)
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('roles');

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'must_change_password' => $user->must_change_password,
                'roles' => $user->roles
                    ->pluck('name')
                    ->values()
                    ->all(),
                'permissions' => $user->getAllPermissions()
                    ->pluck('name')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Revoke current access token (requires auth:sanctum)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Berhasil logout');
    }

    /**
     * Change user's password and reset must_change_password flag
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed|different:current_password|regex:/^(?=.*[A-Za-z])(?=.*[0-9]).+$/',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak cocok.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->save();

        return $this->successResponse(null, 'Password berhasil diubah.');
    }
}
