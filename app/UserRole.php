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
}
