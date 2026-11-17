<?php

namespace App\Listeners;

use App\Events\WorkOrderAssigned;
use App\Models\User;
use App\Notifications\WorkOrderAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWorkOrderAssignedNotification implements ShouldQueue
{
    public function handle(WorkOrderAssigned $event): void
    {
        $techId = $event->workOrder->assigned_to_user_id;
        if (! $techId) {
            return;
        }

        /** @var User|null $technician */
        $technician = User::find($techId);
        if (! $technician) {
            return;
        }

        $technician->notify(new WorkOrderAssignedNotification($event->workOrder, $event->actionNote));
    }
}
