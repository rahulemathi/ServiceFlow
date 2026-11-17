<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Support\FilamentOptions;
use App\Jobs\GenerateReportExportJob;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\WorkOrder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class ReportsCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & analytics';

    protected static ?string $navigationLabel = 'Reports Center';

    protected static ?string $title = 'Reports & Analytics Center';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports-center';

    public ?int $selectedBranchId = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isOrganizationAdmin() || $user->isServiceManager() || $user->isAccountant();
    }

    /**
     * @return array{totalInvoiced: float, totalCollected: float, outstandingBalance: float, completedOrders: int, openOrders: int}
     */
    public function getSummaryMetricsProperty(): array
    {
        $tenant = Filament::getTenant();
        $orgId = $tenant instanceof Organization ? $tenant->id : 0;

        $invoicesQuery = Invoice::query()
            ->where('organization_id', $orgId)
            ->when($this->selectedBranchId, fn ($q) => $q->where('branch_id', $this->selectedBranchId));

        $totalInvoiced = (float) (clone $invoicesQuery)->sum('total');
        $totalCollected = (float) (clone $invoicesQuery)->withSum('payments', 'amount')->get()->sum('payments_sum_amount');
        $outstanding = max(0.0, round($totalInvoiced - $totalCollected, 2));

        $ordersQuery = WorkOrder::query()
            ->where('organization_id', $orgId)
            ->when($this->selectedBranchId, fn ($q) => $q->where('branch_id', $this->selectedBranchId));

        $completedOrders = (clone $ordersQuery)->where('status', 'completed')->count();
        $openOrders = (clone $ordersQuery)->whereNotIn('status', ['completed', 'cancelled'])->count();

        return [
            'totalInvoiced' => $totalInvoiced,
            'totalCollected' => $totalCollected,
            'outstandingBalance' => $outstanding,
            'completedOrders' => $completedOrders,
            'openOrders' => $openOrders,
        ];
    }

    /**
     * @return Collection<int, ReportExport>
     */
    public function getRecentExportsProperty(): Collection
    {
        $tenant = Filament::getTenant();
        $orgId = $tenant instanceof Organization ? $tenant->id : 0;

        return ReportExport::query()
            ->where('organization_id', $orgId)
            ->latest('id')
            ->take(10)
            ->get();
    }

    public function exportReportAction(): Action
    {
        return Action::make('exportReport')
            ->label('Export New Report')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->schema([
                Select::make('type')
                    ->label('Report Type')
                    ->options([
                        'financial' => 'Financial & Invoices Ledger',
                        'work_orders' => 'Operations & Work Orders Summary',
                    ])
                    ->default('financial')
                    ->required(),
                Select::make('branch_id')
                    ->label('Filter Branch (Optional)')
                    ->options(FilamentOptions::branches())
                    ->placeholder('All Branches'),
                DatePicker::make('start_date')
                    ->label('Start Date (Optional)'),
                DatePicker::make('end_date')
                    ->label('End Date (Optional)')
                    ->afterOrEqual('start_date'),
            ])
            ->action(function (array $data): void {
                $tenant = Filament::getTenant();

                /** @var ReportExport $export */
                $export = ReportExport::create([
                    'organization_id' => $tenant?->getKey(),
                    'user_id' => Filament::auth()->id(),
                    'type' => $data['type'],
                    'status' => 'pending',
                    'filters' => [
                        'branch_id' => $data['branch_id'] ?? null,
                        'start_date' => $data['start_date'] ?? null,
                        'end_date' => $data['end_date'] ?? null,
                    ],
                ]);

                // Dispatch background export generation
                GenerateReportExportJob::dispatch($export);

                Notification::make()
                    ->title('Export Queued')
                    ->body('Your report export has been queued. You can download the completed CSV once ready.')
                    ->success()
                    ->send();
            });
    }
}
