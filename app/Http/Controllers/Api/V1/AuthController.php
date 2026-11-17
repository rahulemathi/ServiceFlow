<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'organization_id' => ['nullable', 'integer'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password provided.',
            ], 422);
        }

        $orgId = $validated['organization_id']
            ?? $user->memberships()->where('status', 'active')->value('organization_id');

        $membership = null;
        if ($orgId) {
            $membership = Membership::query()
                ->where('organization_id', $orgId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
        }

        $deviceName = $validated['device_name'] ?? 'Mobile Device';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'organization' => $membership?->organization ? [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                    'slug' => $membership->organization->slug,
                ] : null,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = $user->memberships()
            ->with(['organization:id,name,slug', 'roles'])
            ->where('status', 'active')
            ->get()
            ->map(fn (Membership $m) => [
                'organization_id' => $m->organization_id,
                'organization_name' => $m->organization?->name,
                'roles' => $m->roles->pluck('role'),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'memberships' => $memberships,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke the token that was used to authenticate the current request
        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out and revoked current access token.',
        ]);
    }
}
