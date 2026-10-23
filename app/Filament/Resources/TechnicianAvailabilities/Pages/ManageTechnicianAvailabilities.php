<?php

namespace App\Filament\Resources\TechnicianAvailabilities\Pages;

use App\Filament\Resources\TechnicianAvailabilities\TechnicianAvailabilityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTechnicianAvailabilities extends ManageRecords
{
    protected static string $resource = TechnicianAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
