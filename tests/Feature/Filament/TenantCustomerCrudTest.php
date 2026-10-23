<?php

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('creates a customer owned by the active organization', function () {
    $organization = Organization::create([
        'name' => 'Northside Service',
        'slug' => 'northside-service',
    ]);
    $otherOrganization = Organization::create([
        'name' => 'Westside Service',
        'slug' => 'westside-service',
    ]);
    $user = User::factory()->create();
    $membership = Membership::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);
    $membership->assignRole(UserRole::OrganizationOwner);
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($organization);

    Livewire::test(ManageCustomers::class)
        ->callAction(TestAction::make('create'), data: [
            'name' => 'Northside customer',
            'email' => 'customer@example.test',
            'phone' => '555-0100',
            'branch_id' => null,
            'notes' => 'Created from the tenant panel.',
        ]);

    $customer = Customer::where('name', 'Northside customer')->firstOrFail();

    expect($customer->organization_id)->toBe($organization->id)
        ->and(Customer::where('organization_id', $otherOrganization->id)->count())->toBe(0);
});
