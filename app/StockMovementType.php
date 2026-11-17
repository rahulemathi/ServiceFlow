<?php

namespace App;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case WorkOrderConsumption = 'work_order_consumption';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase Receipt',
            self::WorkOrderConsumption => 'Work Order Consumption',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::Adjustment => 'Stock Adjustment',
        };
    }
}
