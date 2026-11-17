<?php

namespace App\Listeners;

use App\Events\WorkOrderUpdated;
use App\Models\User;
use App\Notifications\WorkOrderStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWorkOrderUpdatedNotification implements ShouldQueue
{
    public function handle(WorkOrderUpdated $event): void
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

        $technician->notify(new WorkOrderStatusChangedNotification($event->workOrder, $event->actionNote));
    }
}
