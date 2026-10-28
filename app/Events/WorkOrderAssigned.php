<?php

namespace App\Events;

use App\Models\WorkOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkOrder $workOrder,
        public ?string $actionNote = null
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('organization.'.$this->workOrder->organization_id),
        ];

        if ($this->workOrder->assigned_to_user_id) {
            $channels[] = new PrivateChannel('technician.'.$this->workOrder->assigned_to_user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'work-order.assigned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->workOrder->id,
            'work_order_number' => $this->workOrder->work_order_number,
            'title' => $this->workOrder->title,
            'status' => is_object($this->workOrder->status) ? $this->workOrder->status->value : $this->workOrder->status,
            'priority' => is_object($this->workOrder->priority) ? $this->workOrder->priority->value : $this->workOrder->priority,
            'assigned_to_user_id' => $this->workOrder->assigned_to_user_id,
            'organization_id' => $this->workOrder->organization_id,
            'customer_name' => $this->workOrder->customer?->name,
            'action_note' => $this->actionNote ?? "Work order {$this->workOrder->work_order_number} has been assigned.",
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
