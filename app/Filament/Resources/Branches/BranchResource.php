<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
use App\Models\Equipment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        return $user?->isOrganizationAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('code')
                    ->maxLength(50)
                    ->rules([
                        fn (?Branch $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if (blank($value)) {
                                return;
                            }

                            $tenantId = Filament::getTenant()?->getKey();
                            $exists = Branch::where('organization_id', $tenantId)
                                ->where('code', $value)
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->exists();

                            if ($exists) {
                                $fail('A branch with this code already exists in this organization.');
                            }
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->placeholder('—'),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Branch $record, DeleteAction $action): void {
                        if ($record->memberships()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete branch')
                                ->body('This branch has team members assigned to it. Please reassign them before deleting.')
                                ->send();

                            $action->cancel();

                            return;
                        }

                        $hasWorkOrders = WorkOrder::where('branch_id', $record->id)->exists();
                        $hasEquipment = Equipment::where('branch_id', $record->id)->exists();
                        $hasServiceRequests = ServiceRequest::where('branch_id', $record->id)->exists();

                        if ($hasWorkOrders || $hasEquipment || $hasServiceRequests) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete branch')
                                ->body('This branch is associated with active operational records (work orders, service requests, or equipment).')
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranches::route('/'),
        ];
    }
}
