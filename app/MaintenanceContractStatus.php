<?php

namespace App;

enum MaintenanceContractStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
