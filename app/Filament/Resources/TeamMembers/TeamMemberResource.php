<?php

namespace App\Filament\Resources\TeamMembers;

use App\Filament\Resources\Support\FilamentOptions;
use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\UserRole;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TeamMemberResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?string $navigationLabel = 'Team members';

    protected static ?string $modelLabel = 'Team member';

    protected static ?string $pluralModelLabel = 'Team members';

    protected static ?string $recordTitleAttribute = 'user.name';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user?->isOrganizationAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account')
                    ->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Alex Rivera'),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. alex@serviceflow.com')
                            ->rules([
                                fn (string $operation, ?Membership $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($operation, $record): void {
                                    $tenantId = Filament::getTenant()?->getKey();
                                    $exists = Membership::query()
                                        ->where('organization_id', $tenantId)
                                        ->whereHas('user', fn ($q) => $q->where('email', $value))
                                        ->when($operation === 'edit' && $record, fn ($q) => $q->where('id', '!=', $record->getKey()))
                                        ->exists();

                                    if ($exists) {
                                        $fail('A team member with this email already belongs to this organization.');
                                    }
                                },
                            ]),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to retain current password.' : 'Minimum 8 characters recommended.')
                            ->columnSpanFull(),
                        Checkbox::make('must_change_password')
                            ->label('Require password change on next login')
                            ->default(true)
                            ->helperText('When enabled, the user is redirected to set a new password on their first login before gaining access.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Organization Roles & Assignment')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->required()
                            ->options(function (): array {
                                /** @var User|null $currentUser */
                                $currentUser = auth()->user();

                                $options = [
                                    UserRole::Technician->value => 'Technician',
                                    UserRole::Dispatcher->value => 'Dispatcher',
                                    UserRole::ServiceManager->value => 'Service Manager',
                                    UserRole::Accountant->value => 'Accountant',
                                    UserRole::Admin->value => 'Admin',
                                ];

                                if ($currentUser && ($currentUser->isOrganizationOwner() || $currentUser->hasPlatformRole(UserRole::PlatformAdmin))) {
                                    $options[UserRole::OrganizationOwner->value] = 'Organization Owner';
                                }

                                return $options;
                            })
                            ->default([UserRole::Technician->value])
                            ->helperText('Select assigned organization roles.'),
                        Select::make('status')
                            ->label('Membership status')
                            ->options([
                                MembershipStatus::Active->value => 'Active',
                                MembershipStatus::Invited->value => 'Invited',
                                MembershipStatus::Suspended->value => 'Suspended',
                            ])
                            ->default(MembershipStatus::Active->value)
                            ->required(),
                        Select::make('branches')
                            ->label('Assigned branches')
                            ->multiple()
                            ->options(fn (): array => FilamentOptions::branches())
                            ->placeholder('All branches')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon(Heroicon::OutlinedEnvelope),
                TextColumn::make('roles.role')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state instanceof UserRole ? $state->value : (string) $state) {
                        UserRole::Technician->value => 'Technician',
                        UserRole::Dispatcher->value => 'Dispatcher',
                        UserRole::ServiceManager->value => 'Service Manager',
                        UserRole::Accountant->value => 'Accountant',
                        UserRole::Admin->value => 'Admin',
                        UserRole::OrganizationOwner->value => 'Organization Owner',
                        default => (string) $state,
                    })
                    ->color(fn ($state): string => match ($state instanceof UserRole ? $state->value : (string) $state) {
                        UserRole::Technician->value => 'info',
                        UserRole::Admin->value => 'primary',
                        UserRole::Dispatcher->value => 'warning',
                        UserRole::ServiceManager->value => 'purple',
                        UserRole::Accountant->value => 'emerald',
                        UserRole::OrganizationOwner->value => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof MembershipStatus ? ucfirst($state->value) : ucfirst((string) $state))
                    ->color(fn ($state): string => match ($state instanceof MembershipStatus ? $state : MembershipStatus::tryFrom((string) $state)) {
                        MembershipStatus::Active => 'success',
                        MembershipStatus::Invited => 'warning',
                        MembershipStatus::Suspended => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.must_change_password')
                    ->label('Password')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Reset required' : 'Active')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('branches.name')
                    ->label('Branches')
                    ->badge()
                    ->placeholder('All branches'),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        UserRole::Technician->value => 'Technician',
                        UserRole::Dispatcher->value => 'Dispatcher',
                        UserRole::ServiceManager->value => 'Service Manager',
                        UserRole::Accountant->value => 'Accountant',
                        UserRole::Admin->value => 'Admin',
                        UserRole::OrganizationOwner->value => 'Organization Owner',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $role): Builder => $q->whereHas('roles', fn ($rq) => $rq->where('role', $role))
                    )),
                SelectFilter::make('status')
                    ->options([
                        MembershipStatus::Active->value => 'Active',
                        MembershipStatus::Invited->value => 'Invited',
                        MembershipStatus::Suspended->value => 'Suspended',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(function (Membership $record): bool {
                        /** @var User|null $currentUser */
                        $currentUser = auth()->user();
                        if (! $currentUser) {
                            return false;
                        }

                        if ($currentUser->isOrganizationOwner() || $currentUser->hasPlatformRole(UserRole::PlatformAdmin)) {
                            return true;
                        }

                        // Admins cannot edit an owner's membership
                        return ! $record->roles()->where('role', UserRole::OrganizationOwner->value)->exists();
                    })
                    ->mutateRecordDataUsing(function (Membership $record): array {
                        return [
                            'name' => $record->user?->name,
                            'email' => $record->user?->email,
                            'must_change_password' => (bool) $record->user?->must_change_password,
                            'status' => $record->status instanceof MembershipStatus ? $record->status->value : (string) $record->status,
                            'roles' => $record->roles->map(fn ($r) => $r->role instanceof UserRole ? $r->role->value : (string) $r->role)->all(),
                            'branches' => $record->branches->pluck('id')->all(),
                        ];
                    })
                    ->using(function (Membership $record, array $data): Membership {
                        /** @var User|null $currentUser */
                        $currentUser = auth()->user();

                        $selectedRoles = collect($data['roles'] ?? [])
                            ->map(fn ($r) => is_string($r) ? UserRole::tryFrom($r) : $r)
                            ->filter();

                        $canAssignOwner = $currentUser && ($currentUser->isOrganizationOwner() || $currentUser->hasPlatformRole(UserRole::PlatformAdmin));

                        // Privilege escalation check
                        if (! $canAssignOwner && $selectedRoles->contains(fn ($r) => $r === UserRole::OrganizationOwner)) {
                            throw ValidationException::withMessages([
                                'roles' => 'You do not have permission to grant Organization Owner privileges.',
                            ]);
                        }

                        // Protect final owner from having owner role removed
                        $hadOwner = $record->roles()->where('role', UserRole::OrganizationOwner->value)->exists();
                        $willHaveOwner = $selectedRoles->contains(fn ($r) => $r === UserRole::OrganizationOwner);

                        if ($hadOwner && ! $willHaveOwner) {
                            $remainingOwners = Membership::query()
                                ->where('organization_id', $record->organization_id)
                                ->where('id', '!=', $record->id)
                                ->where('status', MembershipStatus::Active)
                                ->whereHas('roles', fn ($q) => $q->where('role', UserRole::OrganizationOwner->value))
                                ->count();

                            if ($remainingOwners === 0) {
                                throw ValidationException::withMessages([
                                    'roles' => 'Cannot remove the Organization Owner role from the final active owner.',
                                ]);
                            }
                        }

                        if ($record->user) {
                            $userUpdates = [
                                'name' => $data['name'],
                                'email' => $data['email'],
                                'must_change_password' => (bool) ($data['must_change_password'] ?? false),
                            ];

                            if (filled($data['password'] ?? null)) {
                                $userUpdates['password'] = Hash::make($data['password']);
                            }

                            $record->user->update($userUpdates);
                        }

                        $record->update([
                            'status' => $data['status'] ?? $record->status,
                        ]);

                        $record->roles()->whereNotIn('role', $selectedRoles->pluck('value'))->delete();

                        foreach ($selectedRoles as $role) {
                            if ($role instanceof UserRole && $role->isOrganizationRole()) {
                                $record->assignRole($role);
                            }
                        }

                        if (isset($data['branches'])) {
                            $record->branches()->syncWithPivotValues($data['branches'], [
                                'organization_id' => Filament::getTenant()->getKey(),
                            ]);
                        }

                        return $record;
                    }),
                DeleteAction::make()
                    ->visible(function (Membership $record): bool {
                        /** @var User|null $currentUser */
                        $currentUser = auth()->user();
                        if (! $currentUser) {
                            return false;
                        }

                        if ($currentUser->isOrganizationOwner() || $currentUser->hasPlatformRole(UserRole::PlatformAdmin)) {
                            return true;
                        }

                        // Admins cannot delete an owner's membership
                        return ! $record->roles()->where('role', UserRole::OrganizationOwner->value)->exists();
                    })
                    ->before(function (Membership $record, DeleteAction $action): void {
                        if ($record->user_id === auth()->id()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete your own membership')
                                ->send();

                            $action->cancel();

                            return;
                        }

                        $isOwner = $record->roles()->where('role', UserRole::OrganizationOwner->value)->exists();
                        if ($isOwner) {
                            $remainingOwners = Membership::query()
                                ->where('organization_id', $record->organization_id)
                                ->where('id', '!=', $record->id)
                                ->where('status', MembershipStatus::Active)
                                ->whereHas('roles', fn ($q) => $q->where('role', UserRole::OrganizationOwner->value))
                                ->count();

                            if ($remainingOwners === 0) {
                                Notification::make()
                                    ->danger()
                                    ->title('Action prohibited')
                                    ->body('Cannot delete the final organization owner.')
                                    ->send();

                                $action->cancel();

                                return;
                            }
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Filament::getTenant()?->getKey());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeamMembers::route('/'),
        ];
    }
}
