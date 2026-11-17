<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ProcessedWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-Signature');
        $expectedSignature = hash_hmac('sha256', $request->getContent(), (string) config('app.key'));

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $eventId = (string) $request->input('event_id');
        $eventType = (string) $request->input('event');
        $organizationId = (int) $request->input('organization_id');

        if (blank($eventId) || blank($eventType) || blank($organizationId)) {
            return response()->json(['message' => 'Missing required webhook fields.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (ProcessedWebhook::where('event_id', $eventId)->exists()) {
            return response()->json(['status' => 'already_processed'], Response::HTTP_OK);
        }

        $organization = Organization::find($organizationId);
        if (! $organization) {
            return response()->json(['message' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        match ($eventType) {
            'subscription.activated' => $this->handleSubscriptionActivated($organization, $request->all()),
            'subscription.payment_failed' => $this->handlePaymentFailed($organization),
            'subscription.canceled' => $this->handleSubscriptionCanceled($organization),
            'subscription.suspended' => $this->handleSubscriptionSuspended($organization),
            default => null,
        };

        ProcessedWebhook::create([
            'provider' => (string) ($request->input('provider') ?? 'stripe'),
            'event_id' => $eventId,
            'event_type' => $eventType,
            'processed_at' => now(),
        ]);

        return response()->json(['status' => 'success'], Response::HTTP_OK);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionActivated(Organization $organization, array $payload): void
    {
        $organization->update([
            'subscription_status' => 'active',
            'plan' => $payload['plan'] ?? $organization->plan,
            'grace_period_ends_at' => null,
            'subscription_ends_at' => isset($payload['ends_at']) ? now()->parse((string) $payload['ends_at']) : now()->addMonth(),
            'max_users' => $payload['max_users'] ?? $organization->max_users,
            'max_branches' => $payload['max_branches'] ?? $organization->max_branches,
            'max_monthly_work_orders' => $payload['max_monthly_work_orders'] ?? $organization->max_monthly_work_orders,
        ]);
    }

    protected function handlePaymentFailed(Organization $organization): void
    {
        $organization->update([
            'subscription_status' => 'past_due',
            'grace_period_ends_at' => now()->addDays(7),
        ]);
    }

    protected function handleSubscriptionCanceled(Organization $organization): void
    {
        $organization->update([
            'subscription_status' => 'canceled',
            'subscription_ends_at' => now(),
        ]);
    }

    protected function handleSubscriptionSuspended(Organization $organization): void
    {
        $organization->update([
            'subscription_status' => 'suspended',
        ]);
    }
}
