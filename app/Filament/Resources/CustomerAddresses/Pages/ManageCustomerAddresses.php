<?php

namespace App\Filament\Resources\CustomerAddresses\Pages;

use App\Filament\Resources\CustomerAddresses\CustomerAddressResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerAddresses extends ManageRecords
{
    protected static string $resource = CustomerAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
