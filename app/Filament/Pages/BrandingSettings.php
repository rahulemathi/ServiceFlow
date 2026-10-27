<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BrandingSettings extends Page
{
    protected string $view = 'filament.pages.branding-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'System settings';

    protected static ?string $navigationLabel = 'Branding & Logo';

    protected static ?string $title = 'Branding & Customization';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) ($user?->hasPlatformRole(UserRole::PlatformAdmin) || $user?->isOrganizationOwner());
    }

    public function mount(): void
    {
        $this->form->fill([
            'brand_name' => SystemSetting::get('brand_name') ?: config('app.name', 'ServiceFlow'),
            'brand_tagline' => SystemSetting::get('brand_tagline', 'Field Service & Maintenance Management'),
            'brand_logo' => SystemSetting::get('brand_logo'),
            'brand_logo_height' => SystemSetting::get('brand_logo_height', '2.25rem'),
            'favicon' => SystemSetting::get('favicon'),
            'primary_color' => SystemSetting::get('primary_color', 'blue'),
            'support_email' => SystemSetting::get('support_email', 'support@serviceflow.com'),
            'footer_text' => SystemSetting::get('footer_text', '© '.date('Y').' ServiceFlow. All rights reserved.'),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions()),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Brand Logo & Iconography')
                    ->description('Upload your company logo and browser favicon displayed across the portal.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('brand_logo')
                            ->label('Brand Logo')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
                            ->helperText('Upload a high-resolution logo (PNG, SVG, JPG, or WebP). If unset, the default ServiceFlow logo is used.'),

                        Select::make('brand_logo_height')
                            ->label('Logo Display Height')
                            ->options([
                                '1.75rem' => 'Compact (1.75rem / 28px)',
                                '2.25rem' => 'Standard (2.25rem / 36px)',
                                '2.75rem' => 'Large (2.75rem / 44px)',
                                '3.25rem' => 'Extra Large (3.25rem / 52px)',
                            ])
                            ->default('2.25rem')
                            ->helperText('Height of the logo in the top navigation bar and sidebar.'),

                        FileUpload::make('favicon')
                            ->label('Browser Favicon')
                            ->disk('public')
                            ->directory('branding')
                            ->maxSize(1024)
                            ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'])
                            ->helperText('Square icon displayed in the browser tab (.ico, .png, or .svg).'),
                    ]),

                Section::make('Brand & Portal Identity')
                    ->description('Basic information about your service company displayed in emails, invoices, and the web portal.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('brand_name')
                            ->label('Application / Brand Name')
                            ->required()
                            ->default('ServiceFlow')
                            ->helperText('Displayed in the browser tab, sidebar header, and notifications.'),

                        TextInput::make('brand_tagline')
                            ->label('Brand Tagline / Subtitle')
                            ->placeholder('Field Service & Maintenance Management')
                            ->helperText('Secondary tagline for logins and public headers.'),

                        TextInput::make('support_email')
                            ->label('Support Email Address')
                            ->email()
                            ->placeholder('support@yourcompany.com')
                            ->helperText('Displayed to technicians and customers for help and support.'),

                        TextInput::make('footer_text')
                            ->label('Footer & Copyright Notice')
                            ->placeholder('© 2026 ServiceFlow. All rights reserved.')
                            ->helperText('Displayed in email footers and document outputs.'),
                    ]),

                Section::make('Visual Theme & Accent Color')
                    ->description('Choose the primary theme color for buttons, badges, links, and active states.')
                    ->columns(2)
                    ->schema([
                        Select::make('primary_color')
                            ->label('Primary Accent Color')
                            ->options([
                                'blue' => 'Classic Blue (Default)',
                                'emerald' => 'Emerald Green',
                                'indigo' => 'Royal Indigo',
                                'violet' => 'Modern Violet',
                                'amber' => 'Warm Amber',
                                'rose' => 'Vibrant Rose',
                                'cyan' => 'Fresh Cyan',
                                'teal' => 'Deep Teal',
                            ])
                            ->default('blue')
                            ->required()
                            ->helperText('Changes the primary UI accent color across all portal pages.'),
                    ]),
            ]);
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Branding Settings')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('resetDefaults')
                ->label('Reset to Defaults')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->action('resetDefaults'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value, 'branding');
        }

        // If brand name changed, also update .env APP_NAME
        if (! empty($data['brand_name'])) {
            $this->updateAppNameInEnv($data['brand_name']);
            config(['app.name' => $data['brand_name']]);
        }

        Notification::make()
            ->title('Branding settings saved')
            ->body('Brand customization has been updated and applied across the portal.')
            ->success()
            ->send();
    }

    public function resetDefaults(): void
    {
        $defaults = [
            'brand_name' => 'ServiceFlow',
            'brand_tagline' => 'Field Service & Maintenance Management',
            'brand_logo' => null,
            'brand_logo_height' => '2.25rem',
            'favicon' => null,
            'primary_color' => 'blue',
            'support_email' => 'support@serviceflow.com',
            'footer_text' => '© '.date('Y').' ServiceFlow. All rights reserved.',
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::set($key, $value, 'branding');
        }

        $this->form->fill($defaults);

        Notification::make()
            ->title('Branding reset to defaults')
            ->body('Logo and theme settings have been restored to default values.')
            ->info()
            ->send();
    }

    protected function updateAppNameInEnv(string $name): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        $escaped = '"'.addcslashes($name, '"\\').'"';
        $pattern = '/^APP_NAME=.*/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "APP_NAME={$escaped}", $content);
        } else {
            $content .= "\nAPP_NAME={$escaped}";
        }

        file_put_contents($envPath, $content);
    }
}
