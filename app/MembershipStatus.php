<?php

namespace App;

enum MembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
}
