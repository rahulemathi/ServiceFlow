<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Organization;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

trait InteractsWithServiceFlowFilters
{
    use InteractsWithPageFilters;

    /**
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    protected function dashboardQuery(string $model, string $dateColumn): Builder
    {
        $tenant = $this->dashboardTenant();
        $filters = $this->validatedDashboardFilters($tenant);

        return $model::query()
            ->where('organization_id', $tenant->getKey())
            ->when($filters['startDate'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($dateColumn, '>=', $date))
            ->when($filters['endDate'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($dateColumn, '<=', $date))
            ->when($filters['branchId'] ?? null, fn (Builder $query, int|string $branchId): Builder => $query->where('branch_id', $branchId));
    }

    /**
     * @return array{startDate?: string|null, endDate?: string|null, branchId?: int|string|null}
     */
    protected function validatedDashboardFilters(?Organization $tenant = null): array
    {
        $tenant ??= $this->dashboardTenant();

        return Validator::make($this->pageFilters ?? [], [
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'branchId' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $tenant->getKey()),
            ],
        ])->validate();
    }

    protected function dashboardTenant(): Organization
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Organization, 404);

        return $tenant;
    }
}
