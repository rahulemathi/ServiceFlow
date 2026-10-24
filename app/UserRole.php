<?php

namespace App;

enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case OrganizationOwner = 'organization_owner';
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case Technician = 'technician';

    public function isOrganizationRole(): bool
    {
        return $this !== self::PlatformAdmin;
    }

    /**
     * @return array<int, string>
     */
    public static function organizationRoleValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role): bool => $role->isOrganizationRole())
            ->map(fn (self $role): string => $role->value)
            ->values()
            ->all();
    }
}
