<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $orgId = $request->header('X-Organization-Id')
            ?? $request->input('organization_id')
            ?? $user->memberships()->where('status', 'active')->value('organization_id');

        if (! $orgId) {
            return response()->json([
                'success' => false,
                'message' => 'No active organization found for user.',
            ], 403);
        }

        /** @var Organization|null $organization */
        $organization = Organization::find($orgId);
        if (! $organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        // Validate membership
        $membership = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You are not an active member of this organization.',
            ], 403);
        }

        $request->attributes->set('tenant', $organization);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
