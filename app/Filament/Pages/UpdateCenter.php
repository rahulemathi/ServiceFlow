<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GitHubReleaseChecker;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Throwable;

class UpdateCenter extends Page
{
    protected string $view = 'filament.pages.update-center';

    protected static string|\UnitEnum|null $navigationGroup = 'Updates';

    protected static ?string $navigationLabel = 'Updates';

    protected static ?string $title = 'Application Updates';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array{tag_name: string, name: string, html_url: string, published_at: string|null}|null
     */
    public ?array $latestRelease = null;

    public bool $isUpdateAvailable = false;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasPlatformRole(UserRole::PlatformAdmin);
    }

    public static function getNavigationBadge(): ?string
    {
        $latestVersion = (string) SystemSetting::get('updates_latest_version', '');
        $skippedVersion = (string) SystemSetting::get('updates_skipped_version', '');

        if (
            $latestVersion === ''
            || $latestVersion === $skippedVersion
            || ! version_compare(
                self::normalizeVersion($latestVersion),
                self::normalizeVersion((string) config('app.version', '0.0.0')),
                '>',
            )
        ) {
            return null;
        }

        return '!';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function mount(): void
    {
        $repositoryUrl = (string) SystemSetting::get('updates_repository_url', '');
        $autoCheck = filter_var(
            SystemSetting::get('updates_auto_check', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        $this->form->fill([
            'repository_url' => $repositoryUrl,
            'auto_check' => $autoCheck,
        ]);

        if ($autoCheck && $repositoryUrl !== '') {
            $this->checkForUpdates(showResult: false);
        }
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
                Section::make('GitHub release source')
                    ->description('Configure a public GitHub repository. Updates are checked against its latest published release.')
                    ->schema([
                        TextInput::make('repository_url')
                            ->label('GitHub repository URL')
                            ->placeholder('https://github.com/owner/repository')
                            ->url()
                            ->nullable()
                            ->rules([
                                'regex:/\Ahttps:\/\/github\.com\/[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+(?:\.git)?\/?\z/i',
                            ])
                            ->helperText('Use a public repository URL. Private repositories are not supported.'),

                        Toggle::make('auto_check')
                            ->label('Automatically check for release updates')
                            ->helperText('Checks when this page is opened and shows an alert when a newer release is found. It does not install updates automatically.'),
                    ]),

                Section::make('Release status')
                    ->schema([
                        TextEntry::make('installed_version')
                            ->label('Installed version')
                            ->state((string) config('app.version', '0.0.0')),

                        TextEntry::make('latest_release')
                            ->label('Latest GitHub release')
                            ->state(fn (): string => $this->latestRelease['name'] ?? 'Not checked yet'),

                        TextEntry::make('update_status')
                            ->label('Status')
                            ->state(fn (): string => $this->getReleaseStatus()),
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
                ->label('Save update settings')
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),
        ];
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkForUpdates')
                ->label('Check for updates')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action('checkForUpdates'),

            Action::make('updateNow')
                ->label('Update Now')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('success')
                ->url(
                    fn (): ?string => $this->latestRelease['html_url'] ?? null,
                    shouldOpenInNewTab: true,
                )
                ->visible(fn (): bool => $this->isUpdateAvailable),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $repositoryUrl = trim((string) ($data['repository_url'] ?? ''));
        $autoCheck = (bool) ($data['auto_check'] ?? false);
        $previousRepositoryUrl = (string) SystemSetting::get('updates_repository_url', '');

        SystemSetting::set('updates_repository_url', $repositoryUrl, 'updates');
        SystemSetting::set('updates_auto_check', $autoCheck, 'updates');

        if ($repositoryUrl !== $previousRepositoryUrl) {
            SystemSetting::set('updates_latest_version', null, 'updates');
            SystemSetting::set('updates_skipped_version', null, 'updates');
            SystemSetting::set('updates_remind_until', null, 'updates');
            $this->latestRelease = null;
            $this->isUpdateAvailable = false;
        }

        Notification::make()
            ->title('Update settings saved')
            ->success()
            ->send();
    }

    public function checkForUpdates(bool $showResult = true): void
    {
        $repositoryUrl = (string) SystemSetting::get('updates_repository_url', '');

        if ($repositoryUrl === '') {
            $this->latestRelease = null;
            $this->isUpdateAvailable = false;

            if ($showResult) {
                Notification::make()
                    ->title('GitHub repository not configured')
                    ->body('Add a public GitHub repository URL and save the settings before checking for updates.')
                    ->warning()
                    ->send();
            }

            return;
        }

        try {
            $this->latestRelease = app(GitHubReleaseChecker::class)->latestRelease($repositoryUrl);
        } catch (Throwable $exception) {
            Log::warning('Unable to check for GitHub releases.', [
                'repository_url' => $repositoryUrl,
                'exception' => $exception,
            ]);

            Notification::make()
                ->title('Update check failed')
                ->body('GitHub could not be reached or returned an invalid release response. Please try again later.')
                ->danger()
                ->send();

            return;
        }

        $latestVersion = $this->latestRelease['tag_name'] ?? null;
        SystemSetting::set('updates_latest_version', $latestVersion, 'updates');

        $this->isUpdateAvailable = $latestVersion !== null
            && version_compare(
                self::normalizeVersion($latestVersion),
                self::normalizeVersion((string) config('app.version', '0.0.0')),
                '>',
            );

        if ($this->isUpdateAvailable) {
            if ($showResult || $this->shouldNotifyAutomatically($latestVersion)) {
                $this->sendUpdateNotification();
            }

            return;
        }

        if ($showResult) {
            Notification::make()
                ->title($this->latestRelease === null ? 'No GitHub release found' : 'You are up to date')
                ->success()
                ->send();
        }
    }

    #[On('updatesRemindLater')]
    public function remindLater(): void
    {
        if (! $this->isUpdateAvailable) {
            return;
        }

        SystemSetting::set('updates_remind_until', now()->addDay()->toDateTimeString(), 'updates');

        Notification::make()
            ->title('We will remind you tomorrow')
            ->info()
            ->send();
    }

    #[On('updatesSkipRelease')]
    public function skipRelease(): void
    {
        if (! $this->isUpdateAvailable || $this->latestRelease === null) {
            return;
        }

        SystemSetting::set('updates_skipped_version', $this->latestRelease['tag_name'], 'updates');

        Notification::make()
            ->title('This release was skipped')
            ->info()
            ->send();
    }

    private function getReleaseStatus(): string
    {
        if ($this->latestRelease === null) {
            return 'Check for updates to see whether a newer release is available.';
        }

        return $this->isUpdateAvailable
            ? 'A newer release is available. Update Now opens its GitHub release page.'
            : 'This installation is up to date.';
    }

    private function sendUpdateNotification(): void
    {
        if ($this->latestRelease === null) {
            return;
        }

        Notification::make()
            ->title('New update available')
            ->body("Release {$this->latestRelease['tag_name']} is available.")
            ->warning()
            ->persistent()
            ->actions([
                Action::make('update')
                    ->label('Update')
                    ->button()
                    ->url($this->latestRelease['html_url'], shouldOpenInNewTab: true)
                    ->close(),
                Action::make('remindLater')
                    ->label('Remind me later')
                    ->color('gray')
                    ->dispatch('updatesRemindLater')
                    ->close(),
                Action::make('skip')
                    ->label('Skip')
                    ->color('gray')
                    ->dispatch('updatesSkipRelease')
                    ->close(),
            ])
            ->send();
    }

    private function shouldNotifyAutomatically(string $latestVersion): bool
    {
        if (! filter_var(SystemSetting::get('updates_auto_check', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (SystemSetting::get('updates_skipped_version') === $latestVersion) {
            return false;
        }

        $remindUntil = SystemSetting::get('updates_remind_until');

        return ! is_string($remindUntil) || $remindUntil === '' || ! now()->isBefore($remindUntil);
    }

    private static function normalizeVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }
}
