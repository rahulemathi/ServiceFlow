<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $role_or_title
 * @property bool $is_primary
 * @property-read Organization $organization
 * @property-read Customer $customer
 */
#[Fillable(['organization_id', 'customer_id', 'name', 'email', 'phone', 'role_or_title', 'is_primary'])]
class CustomerContact extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
