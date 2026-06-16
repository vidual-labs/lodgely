<?php

namespace App\Livewire\Settings;

use App\Support\Backup\BackupManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('components.layouts.app')]
class BackupsPage extends Component
{
    use WithFileUploads;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $restoreFile = null;

    public string $restoreConfirmation = '';

    public ?string $notice = null;

    public ?string $error = null;

    public bool $isWorking = false;

    public function mount(): void
    {
        $this->guardOperator();

        // The create/delete/restore mutations now post to BackupController
        // and redirect back here with a one-shot flash. Surface it through
        // the existing notice/error banners.
        $this->notice = session('backups.notice');
        $this->error = session('backups.error');
    }

    public function createBackup(BackupManager $manager): void
    {
        $this->guardOperator();
        $this->resetMessages();

        try {
            $backup = $manager->create();

            Log::info('lodgely.backup.created', [
                'user_id' => auth()->id(),
                'filename' => $backup['filename'],
                'size' => $backup['size'],
            ]);

            $this->notice = __('Backup created: :name (:size)', [
                'name' => $backup['filename'],
                'size' => $this->humanSize($backup['size']),
            ]);
        } catch (Throwable $e) {
            Log::error('lodgely.backup.create_failed', ['user_id' => auth()->id(), 'error' => $e->getMessage()]);
            $this->error = __('Could not create a backup: :error', ['error' => $e->getMessage()]);
        }
    }

    public function deleteBackup(BackupManager $manager, string $filename): void
    {
        $this->guardOperator();
        $this->resetMessages();

        $manager->delete($filename);

        Log::info('lodgely.backup.deleted', ['user_id' => auth()->id(), 'filename' => $filename]);

        $this->notice = __('Deleted :name.', ['name' => $filename]);
    }

    protected function rules(): array
    {
        return [
            'restoreFile' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'restoreConfirmation' => ['required', 'string'],
        ];
    }

    public function restoreBackup(BackupManager $manager): void
    {
        $this->guardOperator();
        $this->resetMessages();

        $this->validate();

        if (strtoupper(trim($this->restoreConfirmation)) !== 'RESTORE') {
            $this->addError('restoreConfirmation', __('Type RESTORE in capital letters to confirm.'));
            return;
        }

        $this->isWorking = true;

        $storedPath = $this->restoreFile->store('backup-uploads', 'local');
        $absolute = storage_path('app/private/'.$storedPath);

        try {
            Log::warning('lodgely.backup.restore_started', [
                'user_id' => auth()->id(),
                'original_name' => $this->restoreFile->getClientOriginalName(),
            ]);

            $manager->restore($absolute);

            Log::warning('lodgely.backup.restore_finished', ['user_id' => auth()->id()]);

            // The dump just dropped and recreated every table, including
            // sessions — the current session is no longer valid. Send the
            // operator back to the login screen rather than rendering
            // against a connection that thinks it's still authenticated.
            auth()->logout();
            session()->invalidate();
            session()->regenerateToken();

            $this->redirectRoute('login', navigate: false);
            return;
        } catch (Throwable $e) {
            Log::error('lodgely.backup.restore_failed', ['user_id' => auth()->id(), 'error' => $e->getMessage()]);
            $this->error = __('Restore failed: :error', ['error' => $e->getMessage()]);
        } finally {
            $this->isWorking = false;
            @unlink($absolute);
            $this->reset(['restoreFile', 'restoreConfirmation']);
        }
    }

    public function render(): View
    {
        return view('livewire.settings.backups-page', [
            'backups' => array_map(
                fn (array $b) => $b + ['size_human' => $this->humanSize($b['size'])],
                app(BackupManager::class)->list(),
            ),
        ]);
    }

    private function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 * 1024 * 1024 => number_format($bytes / (1024 * 1024 * 1024), 2).' GB',
            $bytes >= 1024 * 1024 => number_format($bytes / (1024 * 1024), 2).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 1).' KB',
            default => $bytes.' B',
        };
    }

    private function resetMessages(): void
    {
        $this->notice = null;
        $this->error = null;
        $this->resetErrorBag();
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
