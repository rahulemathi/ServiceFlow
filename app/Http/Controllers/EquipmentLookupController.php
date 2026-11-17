<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentLookupController extends Controller
{
    public function show(Request $request, string $serial): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return redirect()->guest('/admin/login');
        }

        $tenant = Filament::getTenant();

        if (! $tenant) {
            $membership = $user->currentMembership();
            $tenant = $membership ? $membership->organization : null;
        }

        if (! $tenant) {
            /** @var Membership|null $activeMembership */
            $activeMembership = $user->memberships()->where('status', 'active')->first();
            $tenant = $activeMembership ? $activeMembership->organization : null;
        }

        $organization = $tenant instanceof Organization ? $tenant : null;

        if (! $organization) {
            abort(403, 'You must belong to an active organization to look up equipment.');
        }

        /** @var Equipment|null $equipment */
        $equipment = Equipment::query()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($serial): void {
                $q->where('serial_number', $serial)
                    ->orWhere('id', is_numeric($serial) ? (int) $serial : 0);
            })
            ->with(['customer', 'address', 'branch', 'workOrders.assignedTo', 'serviceRequests'])
            ->first();

        // Strict cross-tenant protection: Return 404 if not found in this organization
        if (! $equipment) {
            abort(404, 'Equipment not found in this organization.');
        }

        // Branch access check
        if (! $user->hasBranchAccess($equipment->branch_id, $organization)) {
            abort(403, 'You do not have access to equipment assigned to this branch.');
        }

        return view('equipment.lookup', [
            'equipment' => $equipment,
            'tenant' => $organization,
            'user' => $user,
        ]);
    }
}
