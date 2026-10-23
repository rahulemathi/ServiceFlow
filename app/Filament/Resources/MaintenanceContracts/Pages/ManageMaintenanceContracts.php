<?php

namespace App\Filament\Resources\MaintenanceContracts\Pages;

use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMaintenanceContracts extends ManageRecords
{
    protected static string $resource = MaintenanceContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
