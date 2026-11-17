<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkOrderAssignedNotification extends Notification implements ShouldQueue
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

        if (NotificationPreference::isChannelEnabled($this->workOrder->organization_id, $notifiable->id, 'work_order_assigned', 'database')) {
            $channels[] = 'database';
        }

        if (NotificationPreference::isChannelEnabled($this->workOrder->organization_id, $notifiable->id, 'work_order_assigned', 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $scheduledTime = $this->workOrder->scheduled_start_at
            ? $this->workOrder->scheduled_start_at->format('M d, Y @ g:i A')
            : 'Unscheduled';

        return (new MailMessage)
            ->subject("New Job Assigned: {$this->workOrder->work_order_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned to work order **{$this->workOrder->work_order_number}**: {$this->workOrder->title}.")
            ->line("Customer: {$this->workOrder->customer?->name}")
            ->line("Scheduled Start: {$scheduledTime}")
            ->action('View Job in Workspace', url('/app/technician-workspace'))
            ->line('Thank you for delivering excellent field service!');
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
            'title' => $this->workOrder->title,
            'type' => 'work_order_assigned',
            'message' => "You have been assigned to job {$this->workOrder->work_order_number}: {$this->workOrder->title}",
        ];
    }
}
