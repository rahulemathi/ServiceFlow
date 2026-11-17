<?php

namespace App;

enum EquipmentStatus: string
{
    case Active = 'active';
    case InRepair = 'in_repair';
    case Decommissioned = 'decommissioned';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::InRepair => 'In Repair',
            self::Decommissioned => 'Decommissioned',
            self::Retired => 'Retired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::InRepair => 'warning',
            self::Decommissioned => 'danger',
            self::Retired => 'gray',
        };
    }
}
