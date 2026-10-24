<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Widgets\RecentServiceRequestsWidget;
use App\Filament\Widgets\ServiceOverviewWidget;
use App\Filament\Widgets\ServiceRequestTrendWidget;
use App\Filament\Widgets\TechnicianUtilizationWidget;
use App\Filament\Widgets\UpcomingMaintenanceWidget;
use App\Filament\Widgets\UpcomingWorkOrdersWidget;
use App\Filament\Widgets\WorkOrderStatusChartWidget;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\ServicePriority;
use App\ServiceRequestStatus;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Carbon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'Dashboard';

    public function filtersForm(Schema $schema): Schema
    {
        $tenant = Filament::getTenant();

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('From Date')
                            ->prefixIcon(Heroicon::OutlinedCalendarDays)
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->placeholder('YYYY-MM-DD')
                            ->closeOnDateSelection()
                            ->live()
                            ->default(now()->startOfMonth()->toDateString())
                            ->maxDate(fn (Get $get): ?string => $get('endDate'))
                            ->rules([
                                'nullable',
                                'date',
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $endDate = $get('endDate');
                                    if ($endDate && Carbon::parse($value)->toDateString() > Carbon::parse($endDate)->toDateString()) {
                                        $fail('The From Date must be a date before or equal to To Date.');
                                    }
                                },
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'md' => 1,
                                'lg' => 2,
                            ]),
                        DatePicker::make('endDate')
                            ->label('To Date')
                            ->prefixIcon(Heroicon::OutlinedCalendarDays)
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->placeholder('YYYY-MM-DD')
                            ->closeOnDateSelection()
                            ->live()
                            ->default(today()->addDays(30)->toDateString())
                            ->minDate(fn (Get $get): ?string => $get('startDate'))
                            ->rules([
                                'nullable',
                                'date',
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $startDate = $get('startDate');
                                    if ($startDate && Carbon::parse($value)->toDateString() < Carbon::parse($startDate)->toDateString()) {
                                        $fail('The To Date must be a date after or equal to From Date.');
                                    }
                                },
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'md' => 1,
                                'lg' => 2,
                            ]),
                        Select::make('branchId')
                            ->label('Branch')
                            ->prefixIcon(Heroicon::OutlinedBuildingOffice)
                            ->options(fn (): array => Branch::query()
                                ->where('organization_id', $tenant instanceof Organization ? $tenant->getKey() : null)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('All branches')
                            ->columnSpan([
                                'default' => 1,
                                'md' => 2,
                                'lg' => 3,
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'lg' => 7,
                    ])
                    ->extraAttributes([
                        'class' => 'relative z-20 overflow-visible shadow-xs',
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newServiceRequest')
                ->label('New service request')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => (bool) (auth()->user()?->isOrganizationAdmin() || auth()->user()?->isDispatcher()))
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(FilamentOptions::customers())
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('branch_id')->options(FilamentOptions::branches())->searchable()->placeholder('No branch'),
                    Select::make('customer_address_id')
                        ->label('Service address')
                        ->options(fn (Get $get): array => FilamentOptions::addressesForCustomer($get('customer_id')))
                        ->searchable()
                        ->placeholder('No specific address'),
                    Select::make('equipment_id')
                        ->options(fn (Get $get): array => FilamentOptions::equipmentForCustomer($get('customer_id')))
                        ->searchable()
                        ->placeholder('No equipment'),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->required()
                        ->columnSpanFull(),
                    Select::make('priority')
                        ->options(self::enumOptions(ServicePriority::cases()))
                        ->default(ServicePriority::Normal->value)
                        ->required(),
                    DateTimePicker::make('requested_at')->default(now())->seconds(false),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();

                    abort_unless($tenant instanceof Organization, 404);

                    ServiceRequest::create([
                        ...$data,
                        'organization_id' => $tenant->getKey(),
                        'status' => ServiceRequestStatus::Open,
                    ]);
                })
                ->successNotificationTitle('Service request created'),
        ];
    }

    public function getWidgets(): array
    {
        return [
            ServiceOverviewWidget::class,
            ServiceRequestTrendWidget::class,
            WorkOrderStatusChartWidget::class,
            TechnicianUtilizationWidget::class,
            UpcomingWorkOrdersWidget::class,
            RecentServiceRequestsWidget::class,
            UpcomingMaintenanceWidget::class,
            AccountWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    /**
     * @param  array<\BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = str($case->value)->replace('_', ' ')->title()->toString();
        }

        return $options;
    }
}
