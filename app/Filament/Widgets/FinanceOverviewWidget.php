<?php

namespace App\Filament\Widgets;

use App\CommercialStatus;
use App\Filament\Widgets\Concerns\InteractsWithServiceFlowFilters;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithServiceFlowFilters;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isAccountant()
            || $user->isOrganizationOwner()
            || $user->hasPlatformRole(UserRole::PlatformAdmin);
    }

    protected function getStats(): array
    {
        $tenant = $this->dashboardTenant();

        // Outstanding invoices balance
        $outstandingTotal = $this->dashboardQuery(Invoice::class, 'issued_at')
            ->whereIn('status', [
                CommercialStatus::Sent->value,
                CommercialStatus::PartiallyPaid->value,
                CommercialStatus::Overdue->value,
            ])
            ->withSum('payments', 'amount')
            ->get(['id', 'total'])
            ->sum(fn (Invoice $invoice): float => max(
                0,
                (float) $invoice->total - (float) ($invoice->payments_sum_amount ?? 0),
            ));

        // Overdue invoices count & amount
        $overdueInvoices = $this->dashboardQuery(Invoice::class, 'issued_at')
            ->where('status', CommercialStatus::Overdue->value)
            ->withSum('payments', 'amount')
            ->get(['id', 'total']);

        $overdueCount = $overdueInvoices->count();
        $overdueTotal = $overdueInvoices->sum(fn (Invoice $invoice): float => max(
            0,
            (float) $invoice->total - (float) ($invoice->payments_sum_amount ?? 0),
        ));

        // Total payments received in the period
        $paymentsSum = $this->dashboardQuery(Payment::class, 'received_at')
            ->sum('amount');

        // Estimates awaiting action (Sent or Draft)
        $estimatesPending = $this->dashboardQuery(Estimate::class, 'created_at')
            ->whereIn('status', [CommercialStatus::Draft->value, CommercialStatus::Sent->value])
            ->count();

        return [
            Stat::make('Outstanding Balance', number_format($outstandingTotal, 2).' INR')
                ->description('Unpaid active invoices')
                ->color($outstandingTotal > 0 ? 'warning' : 'success'),

            Stat::make('Overdue Invoices', "{$overdueCount} (".number_format($overdueTotal, 2).' INR)')
                ->description('Requires payment follow-up')
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('Payments Received', number_format($paymentsSum, 2).' INR')
                ->description('Reconciled transactions in period')
                ->color('success'),

            Stat::make('Estimates Awaiting Action', $estimatesPending)
                ->description('Pending customer approval')
                ->color('info'),
        ];
    }
}
