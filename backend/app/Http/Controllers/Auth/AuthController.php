<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $agent = Agent::where('email', $request->email)->first();

        if (! $agent || ! Hash::check($request->password, $agent->password_hash)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_CREDENTIALS',
                    'message' => 'Email ou mot de passe incorrect.',
                    'status'  => 401,
                ],
            ], 401);
        }

        $request->session()->regenerate();
        Auth::guard('web')->login($agent);

        return response()->json([
            'data' => [
                'id'               => $agent->id,
                'prenom'           => $agent->prenom,
                'nom'              => $agent->nom,
                'cooperative_id'   => $agent->cooperative_id,
                'cooperative_code' => $agent->cooperative->code,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }
}
