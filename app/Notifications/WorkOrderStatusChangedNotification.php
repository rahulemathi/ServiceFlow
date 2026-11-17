<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkOrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public ?string $actionNote = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isChannelEnabled($this->workOrder->organization_id, $notifiable->id, 'work_order_updated', 'database')) {
            $channels[] = 'database';
        }

        if (NotificationPreference::isChannelEnabled($this->workOrder->organization_id, $notifiable->id, 'work_order_updated', 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $statusStr = $this->workOrder->status instanceof \BackedEnum
            ? $this->workOrder->status->value
            : (string) $this->workOrder->status;

        return (new MailMessage)
            ->subject("Work Order Status Update: {$this->workOrder->work_order_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Work order **{$this->workOrder->work_order_number}** has transitioned to: **{$statusStr}**.")
            ->when($this->workOrder->status_reason, fn ($m) => $m->line("Reason: {$this->workOrder->status_reason}"))
            ->action('View Work Order', url("/app/work-orders/{$this->workOrder->id}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'organization_id' => $this->workOrder->organization_id,
            'work_order_id' => $this->workOrder->id,
            'work_order_number' => $this->workOrder->work_order_number,
            'status' => $this->workOrder->status instanceof \BackedEnum ? $this->workOrder->status->value : (string) $this->workOrder->status,
            'type' => 'work_order_updated',
            'message' => $this->actionNote ?? "Work order {$this->workOrder->work_order_number} updated to {$this->workOrder->status->value}",
        ];
    }
}
