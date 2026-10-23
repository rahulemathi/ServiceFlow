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
        return Branch::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
    public static function technicians(): array
    {
        return User::query()
            ->whereHas('memberships', function ($query): void {
                $query
                    ->where('organization_id', Filament::getTenant()?->getKey())
                    ->where('status', 'active')
                    ->whereHas('roles', fn ($roles) => $roles->where('role', UserRole::Technician->value));
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
