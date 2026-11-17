<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['organization_id', 'branch_id', 'name', 'email', 'phone', 'notes'])]
class Customer extends Model
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * @return HasMany<CustomerNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<Estimate, $this>
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Return a unified, chronological service & financial activity timeline.
     *
     * @return Collection<int, array{id: int, type: string, title: string, subtitle: string, status: string|null, date: Carbon, amount: float|null}>
     */
    public function timeline(): Collection
    {
        $events = collect();

        foreach ($this->serviceRequests()->latest()->limit(50)->get() as $sr) {
            /** @var ServiceRequest $sr */
            $srPriority = $sr->priority instanceof \BackedEnum ? $sr->priority->value : (string) $sr->priority;
            $srStatus = $sr->status instanceof \BackedEnum ? $sr->status->value : (string) $sr->status;

            $events->push([
                'id' => $sr->id,
                'type' => 'Service Request',
                'title' => $sr->request_number.' — '.$sr->title,
                'subtitle' => 'Priority: '.ucfirst($srPriority),
                'status' => $srStatus,
                'date' => $sr->created_at,
                'amount' => null,
            ]);
        }

        foreach ($this->workOrders()->with('assignedTo')->latest()->limit(50)->get() as $wo) {
            /** @var WorkOrder $wo */
            $woStatus = $wo->status instanceof \BackedEnum ? $wo->status->value : (string) $wo->status;
            $techName = $wo->assignedTo instanceof User ? $wo->assignedTo->name : null;

            $events->push([
                'id' => $wo->id,
                'type' => 'Work Order',
                'title' => $wo->work_order_number.' — '.$wo->title,
                'subtitle' => $techName ? 'Assigned to '.$techName : 'Unassigned',
                'status' => $woStatus,
                'date' => $wo->created_at,
                'amount' => null,
            ]);
        }

        foreach ($this->estimates()->latest()->limit(50)->get() as $est) {
            /** @var Estimate $est */
            $estStatus = $est->status instanceof \BackedEnum ? $est->status->value : (string) $est->status;
            $expiresText = $est->expires_at ? (string) $est->expires_at : 'N/A';

            $events->push([
                'id' => $est->id,
                'type' => 'Estimate',
                'title' => $est->estimate_number,
                'subtitle' => 'Expires: '.$expiresText,
                'status' => $estStatus,
                'date' => $est->created_at,
                'amount' => (float) $est->total,
            ]);
        }

        foreach ($this->invoices()->latest()->limit(50)->get() as $inv) {
            /** @var Invoice $inv */
            $invStatus = $inv->status instanceof \BackedEnum ? $inv->status->value : (string) $inv->status;

            $events->push([
                'id' => $inv->id,
                'type' => 'Invoice',
                'title' => $inv->invoice_number,
                'subtitle' => 'Balance: ₹'.number_format((float) $inv->balance, 2),
                'status' => $invStatus,
                'date' => $inv->created_at,
                'amount' => (float) $inv->total,
            ]);
        }

        foreach ($this->notes()->with('author')->latest()->limit(50)->get() as $note) {
            /** @var CustomerNote $note */
            $authorName = $note->author instanceof User ? $note->author->name : 'Team Member';

            $events->push([
                'id' => $note->id,
                'type' => 'Customer Note',
                'title' => $authorName,
                'subtitle' => $note->note,
                'status' => $note->is_pinned ? 'Pinned' : null,
                'date' => $note->created_at,
                'amount' => null,
            ]);
        }

        return $events->sortByDesc('date')->values();
    }
}
