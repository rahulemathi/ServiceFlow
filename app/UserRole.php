<?php

namespace App;

enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case OrganizationOwner = 'organization_owner';
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case ServiceManager = 'service_manager';
    case Technician = 'technician';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform Administrator',
            self::OrganizationOwner => 'Organization Owner',
            self::Admin => 'Organization Administrator',
            self::Dispatcher => 'Dispatcher',
            self::ServiceManager => 'Service Manager',
            self::Technician => 'Technician',
            self::Accountant => 'Accountant',
        };
    }

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
