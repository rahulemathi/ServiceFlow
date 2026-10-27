<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSettings extends Page
{
    protected string $view = 'filament.pages.mail-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'System settings';

    protected static ?string $navigationLabel = 'Mail Settings';

    protected static ?string $title = 'SMTP Mail Configuration';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasPlatformRole(UserRole::PlatformAdmin);
    }

    public function mount(): void
    {
        $currentHost = (string) env('MAIL_HOST', '');
        $currentMailer = (string) env('MAIL_MAILER', 'smtp');
        $currentScheme = (string) env('MAIL_SCHEME', '');
        $currentPort = (string) env('MAIL_PORT', '587');
        $currentUsername = (string) env('MAIL_USERNAME', '');
        $currentPassword = (string) env('MAIL_PASSWORD', '');
        $currentFromAddress = (string) env('MAIL_FROM_ADDRESS', '');

        // Normalize string null
        $currentScheme = $currentScheme === 'null' ? '' : $currentScheme;
        $currentUsername = $currentUsername === 'null' ? '' : $currentUsername;
        $currentPassword = $currentPassword === 'null' ? '' : $currentPassword;
        $currentFromAddress = $currentFromAddress === 'null' ? '' : $currentFromAddress;

        $isGmail = $currentHost === 'smtp.gmail.com';
        $provider = $isGmail ? 'gmail' : ($currentHost ? 'other' : 'gmail');

        $this->form->fill([
            'provider' => $provider,
            'mail_mailer' => $provider === 'gmail' ? 'smtp' : ($currentMailer ?: 'smtp'),
            'mail_host' => $provider === 'gmail' ? 'smtp.gmail.com' : $currentHost,
            'mail_port' => $provider === 'gmail' ? '587' : ($currentPort ?: '587'),
            'mail_scheme' => $provider === 'gmail' ? 'tls' : $currentScheme,
            'mail_username' => $currentUsername,
            'mail_password' => $currentPassword,
            'mail_from_address' => $currentFromAddress,
            'mail_from_name' => config('app.name', 'ServiceFlow'),
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
                Section::make('SMTP Provider')
                    ->description('Choose Gmail/Google Workspace to auto-populate mail server settings, or Other for custom SMTP servers.')
                    ->schema([
                        Radio::make('provider')
                            ->label('Mail Provider')
                            ->options([
                                'gmail' => 'Gmail / Google Workspace (Auto-configured)',
                                'other' => 'Other / Custom SMTP Client',
                            ])
                            ->default('gmail')
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function (string $state, Set $set, Get $get): void {
                                if ($state === 'gmail') {
                                    $set('mail_mailer', 'smtp');
                                    $set('mail_host', 'smtp.gmail.com');
                                    $set('mail_port', '587');
                                    $set('mail_scheme', 'tls');
                                    if ($get('mail_username') && ! $get('mail_from_address')) {
                                        $set('mail_from_address', $get('mail_username'));
                                    }
                                } else {
                                    $set('mail_mailer', 'smtp');
                                    $set('mail_host', '');
                                    $set('mail_port', '587');
                                    $set('mail_scheme', '');
                                }
                            }),
                    ]),

                Section::make('Server Connection')
                    ->description('Connection configuration for your SMTP server.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_mailer')
                            ->label('MAIL_MAILER')
                            ->default('smtp')
                            ->required()
                            ->readOnly(fn (Get $get): bool => $get('provider') === 'gmail')
                            ->helperText(fn (Get $get): ?string => $get('provider') === 'gmail' ? 'Auto-locked to smtp for Gmail.' : 'Typically "smtp".'),

                        TextInput::make('mail_scheme')
                            ->label('MAIL_SCHEME (Encryption)')
                            ->placeholder('e.g., tls, ssl, or leave empty')
                            ->readOnly(fn (Get $get): bool => $get('provider') === 'gmail')
                            ->helperText(fn (Get $get): ?string => $get('provider') === 'gmail' ? 'Auto-locked to tls for Gmail.' : 'Encryption protocol (e.g. tls or ssl).'),

                        TextInput::make('mail_host')
                            ->label('MAIL_HOST')
                            ->required()
                            ->readOnly(fn (Get $get): bool => $get('provider') === 'gmail')
                            ->helperText(fn (Get $get): ?string => $get('provider') === 'gmail' ? 'Auto-locked to smtp.gmail.com.' : 'Your SMTP server hostname.'),

                        TextInput::make('mail_port')
                            ->label('MAIL_PORT')
                            ->numeric()
                            ->required()
                            ->readOnly(fn (Get $get): bool => $get('provider') === 'gmail')
                            ->helperText(fn (Get $get): ?string => $get('provider') === 'gmail' ? 'Auto-locked to port 587 for Gmail.' : 'Port (587 for TLS, 465 for SSL, 2525 for Sandbox).'),
                    ]),

                Section::make('Credentials & Sender Details')
                    ->description('Authentication credentials and outgoing sender identification.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_username')
                            ->label('MAIL_USERNAME')
                            ->required()
                            ->rule(fn (Get $get) => $get('provider') === 'gmail' ? 'email' : null)
                            ->placeholder('your-email@gmail.com')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if ($get('provider') === 'gmail' && $state && ! $get('mail_from_address')) {
                                    $set('mail_from_address', $state);
                                }
                            })
                            ->helperText(fn (Get $get): string => $get('provider') === 'gmail'
                                ? 'Your full Gmail or Google Workspace email address.'
                                : 'Username or SMTP API key for your mail client.'),

                        TextInput::make('mail_password')
                            ->label('MAIL_PASSWORD')
                            ->password()
                            ->revealable()
                            ->required()
                            ->placeholder('Enter password or app password')
                            ->helperText(fn (Get $get): string => $get('provider') === 'gmail'
                                ? 'Use a 16-character Google App Password (from Google Account > Security > 2-Step Verification > App passwords).'
                                : 'Password or token for your SMTP server.'),

                        TextInput::make('mail_from_address')
                            ->label('MAIL_FROM_ADDRESS')
                            ->email()
                            ->required()
                            ->placeholder('no-reply@yourdomain.com')
                            ->helperText('The email address displayed as the sender to recipients.'),

                        TextInput::make('mail_from_name')
                            ->label('MAIL_FROM_NAME')
                            ->default(config('app.name', 'ServiceFlow'))
                            ->readOnly()
                            ->helperText('Automatically locked to APP_NAME ('.config('app.name', 'ServiceFlow').').'),
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
                ->label('Save Mail Settings')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('testEmail')
                ->label('Send Test Email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->form([
                    TextInput::make('recipient')
                        ->label('Recipient Email Address')
                        ->email()
                        ->required()
                        ->default(fn (): string => auth()->user()?->email ?? 'admin@example.com')
                        ->helperText('Send a test message using the saved credentials to verify connectivity.'),
                ])
                ->action(function (array $data): void {
                    $this->sendTestEmail($data['recipient']);
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $isGmail = ($data['provider'] ?? 'other') === 'gmail';

        $values = [
            'MAIL_MAILER' => $isGmail ? 'smtp' : ($data['mail_mailer'] ?: 'smtp'),
            'MAIL_SCHEME' => $isGmail ? 'tls' : ($data['mail_scheme'] ?: 'null'),
            'MAIL_HOST' => $isGmail ? 'smtp.gmail.com' : ($data['mail_host'] ?: '127.0.0.1'),
            'MAIL_PORT' => $isGmail ? '587' : ($data['mail_port'] ?: '587'),
            'MAIL_USERNAME' => $data['mail_username'] ?: 'null',
            'MAIL_PASSWORD' => $data['mail_password'] ?: 'null',
            'MAIL_FROM_ADDRESS' => $data['mail_from_address'] ?: ($data['mail_username'] ?: 'hello@example.com'),
            'MAIL_FROM_NAME' => '"${APP_NAME}"',
        ];

        $this->updateEnvironmentFile($values);

        // Update runtime config for immediate application
        config([
            'mail.default' => $values['MAIL_MAILER'],
            'mail.mailers.smtp.transport' => $values['MAIL_MAILER'],
            'mail.mailers.smtp.host' => $values['MAIL_HOST'],
            'mail.mailers.smtp.port' => (int) $values['MAIL_PORT'],
            'mail.mailers.smtp.scheme' => ($values['MAIL_SCHEME'] === 'null' || ! $values['MAIL_SCHEME']) ? null : $values['MAIL_SCHEME'],
            'mail.mailers.smtp.username' => ($values['MAIL_USERNAME'] === 'null' || ! $values['MAIL_USERNAME']) ? null : $values['MAIL_USERNAME'],
            'mail.mailers.smtp.password' => ($values['MAIL_PASSWORD'] === 'null' || ! $values['MAIL_PASSWORD']) ? null : $values['MAIL_PASSWORD'],
            'mail.from.address' => $values['MAIL_FROM_ADDRESS'],
            'mail.from.name' => config('app.name', 'ServiceFlow'),
        ]);

        Notification::make()
            ->title('Mail settings updated')
            ->body('SMTP configuration has been written to .env and applied successfully.')
            ->success()
            ->send();
    }

    public function sendTestEmail(string $recipient): void
    {
        try {
            Mail::raw("Hello!\n\nThis is a test email sent from ServiceFlow to verify that your SMTP client settings are working properly.\n\nTimestamp: ".now()->toDateTimeString(), function ($message) use ($recipient): void {
                $message->to($recipient)
                    ->subject('ServiceFlow — SMTP Configuration Test');
            });

            Notification::make()
                ->title('Test email dispatched')
                ->body("A test message was dispatched to {$recipient}.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to send test email')
                ->body('Error: '.$e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function updateEnvironmentFile(array $values): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            if ($value === null || $value === '' || $value === 'null') {
                $formattedValue = 'null';
            } elseif (is_numeric($value)) {
                $formattedValue = (string) $value;
            } elseif (str_starts_with((string) $value, '"') && str_ends_with((string) $value, '"')) {
                $formattedValue = (string) $value;
            } elseif (preg_match('/\s/', (string) $value) || str_contains((string) $value, '#') || str_contains((string) $value, '$')) {
                $formattedValue = '"'.addcslashes((string) $value, '"\\').'"';
            } else {
                $formattedValue = (string) $value;
            }

            $pattern = "/^{$key}=.*/m";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$formattedValue}", $content);
            } else {
                $content .= "\n{$key}={$formattedValue}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
