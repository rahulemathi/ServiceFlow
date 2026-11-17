<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditOrganizationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Organization profile';
    }

    public static function canView(Model $tenant): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasPlatformRole(UserRole::PlatformAdmin)) {
            return true;
        }

        return $tenant instanceof Organization && $user->isOrganizationOwner($tenant);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Organization name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Acme Services'),

                TextInput::make('slug')
                    ->label('URL slug')
                    ->disabled()
                    ->helperText('Organization slug is permanent and used for tenant portal URLs.'),
            ]);
    }
}
