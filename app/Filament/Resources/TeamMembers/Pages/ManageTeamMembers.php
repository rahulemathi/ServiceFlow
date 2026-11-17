<?php

namespace App\Filament\Resources\TeamMembers\Pages;

use App\Filament\Resources\TeamMembers\TeamMemberResource;
use App\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\UserRole;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageTeamMembers extends ManageRecords
{
    protected static string $resource = TeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New team member')
                ->using(function (array $data): Membership {
                    $mustChangePassword = (bool) ($data['must_change_password'] ?? true);

                    $user = User::where('email', $data['email'])->first();

                    if (! $user) {
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => filled($data['password'] ?? null)
                                ? Hash::make($data['password'])
                                : Hash::make(Str::random(16)),
                            'must_change_password' => $mustChangePassword,
                        ]);
                    }

                    $tenant = Filament::getTenant();

                    $membership = Membership::firstOrCreate(
                        [
                            'organization_id' => $tenant->getKey(),
                            'user_id' => $user->getKey(),
                        ],
                        [
                            'status' => $data['status'] ?? MembershipStatus::Active->value,
                        ],
                    );

                    $membership->update([
                        'status' => $data['status'] ?? MembershipStatus::Active->value,
                    ]);

                    /** @var array<int, string|UserRole> $rawRoles */
                    $rawRoles = is_array($data['roles'] ?? null) ? $data['roles'] : [UserRole::Technician->value];

                    $selectedRoles = collect($rawRoles)
                        ->map(fn ($r) => is_string($r) ? UserRole::tryFrom($r) : $r)
                        ->filter();

                    /** @var User|null $currentUser */
                    $currentUser = auth()->user();
                    $canAssignOwner = $currentUser && ($currentUser->isOrganizationOwner() || $currentUser->hasPlatformRole(UserRole::PlatformAdmin));

                    if (! $canAssignOwner && $selectedRoles->contains(fn ($r) => $r === UserRole::OrganizationOwner)) {
                        throw ValidationException::withMessages([
                            'roles' => 'You do not have permission to grant Organization Owner privileges.',
                        ]);
                    }

                    $membership->roles()->whereNotIn('role', $selectedRoles->pluck('value'))->delete();

                    foreach ($selectedRoles as $role) {
                        if ($role->isOrganizationRole()) {
                            $membership->assignRole($role);
                        }
                    }

                    if (isset($data['branches'])) {
                        $membership->branches()->syncWithPivotValues($data['branches'], [
                            'organization_id' => $tenant->getKey(),
                        ]);
                    }

                    return $membership;
                }),
        ];
    }
}
