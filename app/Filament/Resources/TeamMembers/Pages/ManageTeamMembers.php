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

                    $user = User::firstOrCreate(
                        ['email' => $data['email']],
                        [
                            'name' => $data['name'],
                            'password' => filled($data['password'] ?? null)
                                ? Hash::make($data['password'])
                                : Hash::make(Str::random(16)),
                            'must_change_password' => $mustChangePassword,
                        ],
                    );

                    $userUpdates = [];

                    if (filled($data['name']) && $user->name !== $data['name']) {
                        $userUpdates['name'] = $data['name'];
                    }

                    if (filled($data['password'] ?? null)) {
                        $userUpdates['password'] = Hash::make($data['password']);
                    }

                    if (isset($data['must_change_password'])) {
                        $userUpdates['must_change_password'] = $mustChangePassword;
                    }

                    if (! empty($userUpdates)) {
                        $user->update($userUpdates);
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

                    $selectedRoles = collect($data['roles'] ?? [UserRole::Technician->value])
                        ->map(fn ($r) => is_string($r) ? UserRole::tryFrom($r) : $r)
                        ->filter();

                    $membership->roles()->whereNotIn('role', $selectedRoles->pluck('value'))->delete();

                    foreach ($selectedRoles as $role) {
                        if ($role instanceof UserRole && $role->isOrganizationRole()) {
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
