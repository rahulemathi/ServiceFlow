<?php

namespace App\Filament\Pages\Tenancy;

use App\MembershipStatus;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register organization';
    }

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Organization name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Acme Services')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if ($state) {
                            $set('slug', Str::slug($state));
                        }
                    }),

                TextInput::make('slug')
                    ->label('URL slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Organization::class, 'slug')
                    ->placeholder('e.g. acme-services')
                    ->helperText('Unique URL identifier for your organization portal.')
                    ->alphaDash(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        $slug = ! empty($data['slug']) ? Str::slug((string) $data['slug']) : Str::slug((string) $data['name']);

        $originalSlug = $slug;
        $counter = 1;
        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $organization = Organization::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        $membership = Membership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
        ]);

        $membership->assignRole(UserRole::OrganizationOwner);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
        ]);

        $membership->assignBranch($branch);

        return $organization;
    }
}
