<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * @property Schema $form
 */
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
        $hasPassword = filled(config('mail.mailers.smtp.password'));

        $this->form->fill([
            'mail_mailer' => (string) config('mail.default', 'smtp'),
            'mail_host' => (string) config('mail.mailers.smtp.host', ''),
            'mail_port' => (string) config('mail.mailers.smtp.port', '587'),
            'mail_scheme' => (string) (config('mail.mailers.smtp.scheme') ?? ''),
            'mail_username' => (string) (config('mail.mailers.smtp.username') ?? ''),
            'mail_password_status' => $hasPassword ? '•••••••• (Configured via deployment)' : 'Not configured',
            'mail_from_address' => (string) config('mail.from.address', ''),
            'mail_from_name' => (string) config('mail.from.name', config('app.name', 'ServiceFlow')),
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
            ->id('form');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Deployment-Managed Mail Configuration')
                    ->description('Platform SMTP settings are managed securely via deployment environment variables and Laravel configuration. Values cannot be modified from the web application.')
                    ->schema([
                        Placeholder::make('deployment_notice')
                            ->label('Security Notice')
                            ->content(new HtmlString('<div class="text-sm text-gray-600 dark:text-gray-400">To update mail settings, configure the appropriate environment variables (<code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_MAILER</code>, <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_HOST</code>, <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_PORT</code>, <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_USERNAME</code>, <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_PASSWORD</code>, <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_SCHEME</code>, <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">MAIL_FROM_ADDRESS</code>) in your deployment environment and rebuild or clear the configuration cache.</div>')),
                    ]),

                Section::make('Current Server Connection')
                    ->description('Active mail transport configuration as loaded by Laravel.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_mailer')
                            ->label('MAIL_MAILER (Transport)')
                            ->disabled()
                            ->helperText('Configured mail transport driver (e.g. smtp, log, sendmail).'),

                        TextInput::make('mail_scheme')
                            ->label('MAIL_SCHEME (Encryption)')
                            ->disabled()
                            ->placeholder('None')
                            ->helperText('Encryption protocol (e.g. tls, ssl).'),

                        TextInput::make('mail_host')
                            ->label('MAIL_HOST')
                            ->disabled()
                            ->helperText('SMTP server hostname.'),

                        TextInput::make('mail_port')
                            ->label('MAIL_PORT')
                            ->disabled()
                            ->helperText('SMTP server port (e.g. 587, 465).'),
                    ]),

                Section::make('Credentials & Sender Details')
                    ->description('Authentication status and default sender attributes.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_username')
                            ->label('MAIL_USERNAME')
                            ->disabled()
                            ->placeholder('Not set')
                            ->helperText('Configured SMTP username or API key.'),

                        TextInput::make('mail_password_status')
                            ->label('MAIL_PASSWORD')
                            ->disabled()
                            ->helperText('Password is encrypted / hidden and never exposed to the browser.'),

                        TextInput::make('mail_from_address')
                            ->label('MAIL_FROM_ADDRESS')
                            ->disabled()
                            ->placeholder('Not set')
                            ->helperText('Default outgoing sender email address.'),

                        TextInput::make('mail_from_name')
                            ->label('MAIL_FROM_NAME')
                            ->disabled()
                            ->helperText('Default outgoing sender name.'),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Send Test Email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->form([
                    TextInput::make('recipient')
                        ->label('Recipient Email Address')
                        ->email()
                        ->required()
                        ->default(function (): string {
                            $user = auth()->user();

                            return $user instanceof User ? $user->email : 'admin@example.com';
                        })
                        ->helperText('Send a test message using the server\'s active mail configuration.'),
                ])
                ->action(function (array $data): void {
                    $this->sendTestEmail((string) $data['recipient']);
                }),
        ];
    }

    public function sendTestEmail(string $recipient): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            Mail::raw("Hello!\n\nThis is a test email sent from ServiceFlow to verify that your platform SMTP configuration is working properly.\n\nTimestamp: ".now()->toDateTimeString(), function ($message) use ($recipient): void {
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
}
