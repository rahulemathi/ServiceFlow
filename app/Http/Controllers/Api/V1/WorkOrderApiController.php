<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\WorkOrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkOrderApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Organization $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var User $user */
        $user = $request->user();

        $query = WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->when(! $user->isOrganizationAdmin(), function ($q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id);
            })
            ->when($request->query('status'), function ($q, $status): void {
                $q->where('status', $status);
            })
            ->with(['customer:id,name,phone', 'branch:id,name', 'equipment:id,name,model,serial_number'])
            ->orderBy('scheduled_start_at');

        $paginated = $query->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Organization $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var User $user */
        $user = $request->user();

        /** @var WorkOrder|null $workOrder */
        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->when(! $user->isOrganizationAdmin(), function ($q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id);
            })
            ->with(['customer', 'branch', 'address', 'equipment', 'checklists', 'entries'])
            ->find($id);

        if (! $workOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $workOrder,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        /** @var Organization $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var User $user */
        $user = $request->user();

        /** @var WorkOrder|null $workOrder */
        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->when(! $user->isOrganizationAdmin(), function ($q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id);
            })
            ->find($id);

        if (! $workOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $targetStatus = WorkOrderStatus::tryFrom($validated['status']);
        if (! $targetStatus) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status: {$validated['status']}.",
            ], 422);
        }

        if (! empty($validated['completion_notes'])) {
            $workOrder->completion_notes = $validated['completion_notes'];
            $workOrder->save();
        }

        try {
            $workOrder->transitionTo($targetStatus, $validated['reason'] ?? null, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Unable to transition status.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Work order updated to {$targetStatus->value}.",
            'data' => $workOrder->fresh(),
        ]);
    }

    public function toggleChecklist(Request $request, int $id, int $checklistId): JsonResponse
    {
        /** @var Organization $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var User $user */
        $user = $request->user();

        /** @var WorkOrder|null $workOrder */
        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->when(! $user->isOrganizationAdmin(), function ($q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id);
            })
            ->find($id);

        if (! $workOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found.',
            ], 404);
        }

        /** @var WorkOrderChecklist|null $item */
        $item = $workOrder->checklists()->find($checklistId);
        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist item not found.',
            ], 404);
        }

        if ($item->is_completed) {
            $item->markIncomplete();
        } else {
            $item->markCompleted($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Checklist item toggled.',
            'data' => $item->fresh(),
        ]);
    }

    public function addEntry(Request $request, int $id): JsonResponse
    {
        /** @var Organization $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var User $user */
        $user = $request->user();

        /** @var WorkOrder|null $workOrder */
        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->when(! $user->isOrganizationAdmin(), function ($q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id);
            })
            ->find($id);

        if (! $workOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found.',
            ], 404);
        }

        if ($workOrder->status === WorkOrderStatus::Completed) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add entries to a completed work order.',
            ], 422);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:labor,note,part'],
            'description' => ['required', 'string', 'max:255'],
            'minutes' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'part_id' => ['nullable', 'integer'],
            'stock_location_id' => ['nullable', 'integer'],
        ]);

        $entry = $workOrder->entries()->create([
            'organization_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'minutes' => $validated['minutes'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'unit_price' => $validated['unit_price'] ?? null,
            'part_id' => $validated['part_id'] ?? null,
            'stock_location_id' => $validated['stock_location_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Entry added successfully.',
            'data' => $entry,
        ], 201);
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        /** @var Organization $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var User $user */
        $user = $request->user();

        /** @var WorkOrder|null $workOrder */
        $workOrder = WorkOrder::query()
            ->where('organization_id', $tenant->id)
            ->when(! $user->isOrganizationAdmin(), function ($q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id);
            })
            ->find($id);

        if (! $workOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found.',
            ], 404);
        }

        $validated = $request->validate([
            'customer_signature_name' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $workOrder->signCustomer($validated['customer_signature_name']);

        return response()->json([
            'success' => true,
            'message' => 'Customer signature recorded.',
            'data' => [
                'customer_signature_name' => $workOrder->customer_signature_name,
                'customer_signed_at' => $workOrder->customer_signed_at?->toIso8601String(),
            ],
        ]);
    }
}
