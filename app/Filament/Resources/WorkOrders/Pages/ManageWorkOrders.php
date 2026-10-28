<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkOrders extends ManageRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if (! $tenantId) {
            return [];
        }

        return [
            "echo-private:organization.{$tenantId},.work-order.updated" => '$refresh',
            "echo-private:organization.{$tenantId},.work-order.assigned" => '$refresh',
        ];
    }
}
