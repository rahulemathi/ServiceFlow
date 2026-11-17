<?php

namespace App\Filament\Resources\TeamMembers\Pages;

use App\Filament\Resources\TeamMembers\TeamMemberResource;
use App\MembershipStatus;
use App\Models\Membership;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\UserRole;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageTeamMembers extends ManageRecords
{
    protected static string $resource = TeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inviteMember')
                ->label('Invite team member')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => auth()->user()?->isOrganizationAdmin() ?? false)
                ->form([
                    TextInput::make('email')
                        ->label('Email address')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->placeholder('colleague@example.com')
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                $tenantId = Filament::getTenant()?->getKey();
                                $exists = Membership::query()
                                    ->where('organization_id', $tenantId)
                                    ->whereHas('user', fn ($q) => $q->where('email', $value))
                                    ->exists();

                                if ($exists) {
                                    $fail('A team member with this email already belongs to this organization.');
                                }
                            },
                        ]),
                    Select::make('role')
                        ->label('Assigned role')
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
                        ->default(UserRole::Technician->value),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    if (! $tenant) {
                        return;
                    }

                    /** @var User|null $currentUser */
                    $currentUser = auth()->user();
                    $selectedRole = UserRole::tryFrom((string) $data['role']);

                    if (! $selectedRole || ! $selectedRole->isOrganizationRole()) {
                        throw ValidationException::withMessages([
                            'role' => 'Invalid organization role selected.',
                        ]);
                    }

                    $canAssignOwner = $currentUser && ($currentUser->isOrganizationOwner() || $currentUser->hasPlatformRole(UserRole::PlatformAdmin));
                    if (! $canAssignOwner && $selectedRole === UserRole::OrganizationOwner) {
                        throw ValidationException::withMessages([
                            'role' => 'You do not have permission to grant Organization Owner privileges.',
                        ]);
                    }

                    // Remove any existing pending invitations for this email in this organization
                    OrganizationInvitation::query()
                        ->where('organization_id', $tenant->getKey())
                        ->where('email', strtolower(trim((string) $data['email'])))
                        ->whereNull('accepted_at')
                        ->delete();

                    $token = Str::random(40);
                    $invitation = OrganizationInvitation::create([
                        'organization_id' => $tenant->getKey(),
                        'email' => strtolower(trim((string) $data['email'])),
                        'role' => $selectedRole,
                        'token' => $token,
                        'invited_by' => $currentUser?->id,
                        'expires_at' => now()->addDays(7),
                    ]);

                    $inviteUrl = route('invitations.show', ['token' => $token]);

                    Notification::make()
                        ->success()
                        ->title('Invitation created')
                        ->body("Invitation link created for {$invitation->email}: {$inviteUrl}")
                        ->persistent()
                        ->send();
                }),

            Action::make('pendingInvitations')
                ->label('Pending invitations')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->isOrganizationAdmin() ?? false)
                ->modalHeading('Pending Invitations')
                ->modalDescription('View and manage pending team invitations.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.pages.pending-invitations', [
                    'invitations' => OrganizationInvitation::query()
                        ->with('inviter')
                        ->where('organization_id', Filament::getTenant()?->getKey())
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->latest()
                        ->get(),
                ])),

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

    public function revokeInvitation(int $invitationId): void
    {
        /** @var User|null $currentUser */
        $currentUser = auth()->user();
        if (! $currentUser || ! $currentUser->isOrganizationAdmin()) {
            return;
        }

        $invitation = OrganizationInvitation::query()
            ->where('organization_id', Filament::getTenant()?->getKey())
            ->where('id', $invitationId)
            ->first();

        if ($invitation) {
            $invitation->delete();

            Notification::make()
                ->success()
                ->title('Invitation revoked')
                ->send();
        }
    }
}
