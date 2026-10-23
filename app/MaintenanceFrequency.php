<?php

namespace App;

use Carbon\CarbonInterface;

enum MaintenanceFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public function addInterval(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonth(),
            self::Quarterly => $date->copy()->addMonths(3),
            self::SemiAnnual => $date->copy()->addMonths(6),
            self::Annual => $date->copy()->addYear(),
        };
    }
}
