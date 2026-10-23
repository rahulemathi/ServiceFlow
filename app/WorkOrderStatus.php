<?php

namespace App;

enum WorkOrderStatus: string
{
    case New = 'new';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
