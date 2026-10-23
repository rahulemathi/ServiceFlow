<?php

namespace App\Models;

use App\MaintenanceContractStatus;
use App\MaintenanceFrequency;
use App\WorkOrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'branch_id', 'customer_id', 'equipment_id', 'name', 'frequency', 'starts_at', 'ends_at', 'next_service_at', 'status', 'amount'])]
class MaintenanceContract extends Model
{
    protected $attributes = [
        'status' => 'active',
        'amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'frequency' => MaintenanceFrequency::class,
            'status' => MaintenanceContractStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'next_service_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            $contract->contract_number ??= 'MC-'.Str::upper((string) Str::ulid());
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function generateWorkOrder(?CarbonInterface $serviceDate = null): WorkOrder
    {
        $date = $serviceDate ?? ($this->next_service_at ? Carbon::parse($this->next_service_at) : today());

        $workOrder = WorkOrder::create([
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'equipment_id' => $this->equipment_id,
            'title' => 'Preventive Maintenance: '.$this->name,
            'description' => "Scheduled maintenance under contract {$this->contract_number} ({$this->frequency->value}).",
            'status' => WorkOrderStatus::New->value,
            'scheduled_start_at' => $date->copy()->startOfDay()->addHours(9),
            'scheduled_end_at' => $date->copy()->startOfDay()->addHours(11),
        ]);

        $nextDate = $this->frequency->addInterval($date);

        if ($this->ends_at !== null && $nextDate->greaterThan(Carbon::parse($this->ends_at))) {
            $this->next_service_at = null;
            $this->status = MaintenanceContractStatus::Expired;
        } else {
            $this->next_service_at = $nextDate->toDateString();
        }

        $this->save();

        return $workOrder;
    }
}
