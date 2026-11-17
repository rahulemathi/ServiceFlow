<?php

namespace App\Filament\Resources\Support;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkOrder;
use App\UserRole;
use Closure;
use Filament\Facades\Filament;

class FilamentOptions
{
    /**
     * @param  array<\BackedEnum>  $cases
     * @return array<string, string>
     */
    public static function enum(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = str($case->value)->replace('_', ' ')->title()->toString();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function branches(): array
    {
        $query = Branch::query()
            ->where('organization_id', Filament::getTenant()?->getKey());

        /** @var User|null $user */
        $user = Filament::auth()->user();
        if ($user && ! $user->isOrganizationAdmin() && ! $user->hasPlatformRole(UserRole::PlatformAdmin)) {
            $permitted = $user->permittedBranchIds();
            if (! empty($permitted)) {
                $query->whereIn('id', $permitted);
            }
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, Closure>
     */
    public static function branchValidationRules(): array
    {
        return [
            function (): Closure {
                return function (string $attribute, mixed $value, Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    $tenantId = Filament::getTenant()?->getKey();
                    /** @var Branch|null $branch */
                    $branch = Branch::query()
                        ->where('organization_id', $tenantId)
                        ->where('id', $value)
                        ->first();

                    if (! $branch) {
                        $fail('The selected branch does not exist in this organization.');

                        return;
                    }

                    /** @var User|null $user */
                    $user = Filament::auth()->user();
                    if ($user && ! $user->hasBranchAccess($branch->id)) {
                        $fail('You do not have permission to assign records to this branch.');
                    }
                };
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function customers(): array
    {
        return Customer::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function addresses(): array
    {
        return CustomerAddress::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->with('customer:id,name')
            ->orderBy('city')
            ->get()
            ->mapWithKeys(fn (CustomerAddress $address): array => [
                $address->id => $address->customer->name.' - '.$address->address_line_1.', '.$address->city,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function addressesForCustomer(?string $customerId): array
    {
        if (blank($customerId)) {
            return [];
        }

        return CustomerAddress::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->where('customer_id', $customerId)
            ->orderBy('city')
            ->get()
            ->mapWithKeys(fn (CustomerAddress $address): array => [
                $address->id => ($address->label ?: $address->address_line_1).', '.$address->city,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function equipment(): array
    {
        return Equipment::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->with('customer:id,name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Equipment $equipment): array => [
                $equipment->id => $equipment->customer->name.' - '.$equipment->name,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function equipmentForCustomer(?string $customerId): array
    {
        if (blank($customerId)) {
            return [];
        }

        return Equipment::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->where('customer_id', $customerId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function workOrdersForCustomer(?string $customerId): array
    {
        if (blank($customerId)) {
            return [];
        }

        return WorkOrder::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->pluck('work_order_number', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function technicians(?int $branchId = null): array
    {
        return User::query()
            ->whereHas('memberships', function ($query) use ($branchId): void {
                $query
                    ->where('organization_id', Filament::getTenant()?->getKey())
                    ->where('status', 'active')
                    ->whereHas('roles', fn ($roles) => $roles->where('role', UserRole::Technician->value))
                    ->when($branchId, function ($q) use ($branchId): void {
                        $q->where(function ($sq) use ($branchId): void {
                            $sq->whereDoesntHave('branches')
                                ->orWhereHas('branches', fn ($bq) => $bq->where('branches.id', $branchId));
                        });
                    });
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function payableInvoices(): array
    {
        return Invoice::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->with('customer:id,name')
            ->withSum('payments', 'amount')
            ->orderBy('invoice_number')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->balance > 0)
            ->mapWithKeys(fn (Invoice $invoice): array => [
                $invoice->id => $invoice->invoice_number.' - '.$invoice->customer->name.' ('.$invoice->balance.')',
            ])
            ->all();
    }
}
