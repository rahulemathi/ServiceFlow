<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MaintenanceContract $contract
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isChannelEnabled($this->contract->organization_id, $notifiable->id, 'maintenance_due', 'database')) {
            $channels[] = 'database';
        }

        if (NotificationPreference::isChannelEnabled($this->contract->organization_id, $notifiable->id, 'maintenance_due', 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Upcoming Maintenance Due: {$this->contract->contract_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Preventive maintenance for contract **{$this->contract->name}** ({$this->contract->contract_number}) is due on **{$this->contract->next_service_at?->format('M d, Y')}**.")
            ->line("Customer: {$this->contract->customer?->name}")
            ->action('Review Contract', url('/app/maintenance-contracts'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'organization_id' => $this->contract->organization_id,
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'type' => 'maintenance_due',
            'message' => "Preventive maintenance due for {$this->contract->name}",
        ];
    }
}
