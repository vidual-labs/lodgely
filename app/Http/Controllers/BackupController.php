<?php

namespace App\Http\Controllers;

use App\Livewire\Settings\BackupsPage;
use App\Support\Backup\BackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Native HTML form endpoints for the backup & recovery page.
 *
 * Why this exists in addition to the Livewire component:
 *
 * The restore flow was driven entirely by Livewire — `wire:model` on the
 * file input (an async WithFileUploads temp upload) plus a
 * `wire:submit`/`wire:confirm` action. In production both halves failed
 * the same way the inbox filter-card actions did (see CLAUDE.md): the
 * temp upload hung on "Uploading…" and never completed, and the submit
 * click was silently dropped by the morph layer, so "Restore and
 * overwrite database" did nothing.
 *
 * A bare multipart `<form method="POST">` cannot fail that way: the
 * browser uploads the file as part of an ordinary request, Laravel
 * routes it here, we run the (destructive) restore, sign the operator
 * out, and redirect to the login screen. Create and delete moved to the
 * same native-form path for consistency.
 *
 * The Livewire actions on {@see BackupsPage} stay
 * around for tests and any programmatic caller, but the UI no longer
 * drives them.
 */
class BackupController extends Controller
{
    public function create(Request $request, BackupManager $manager): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        try {
            $backup = $manager->create();

            Log::info('lodgely.backup.created', [
                'user_id' => $request->user()->id,
                'filename' => $backup['filename'],
                'size' => $backup['size'],
            ]);

            return redirect()->route('settings.backups')->with(
                'backups.notice',
                __('Backup created: :name (:size)', [
                    'name' => $backup['filename'],
                    'size' => $this->humanSize($backup['size']),
                ]),
            );
        } catch (Throwable $e) {
            Log::error('lodgely.backup.create_failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return redirect()->route('settings.backups')->with(
                'backups.error',
                __('Could not create a backup: :error', ['error' => $e->getMessage()]),
            );
        }
    }

    public function destroy(Request $request, BackupManager $manager): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $filename = (string) $request->input('filename');

        $manager->delete($filename);

        Log::info('lodgely.backup.deleted', ['user_id' => $request->user()->id, 'filename' => $filename]);

        return redirect()->route('settings.backups')->with('backups.notice', __('Deleted :name.', ['name' => $filename]));
    }

    /**
     * Restore the database from an uploaded archive. Destructive — the
     * dump is restored with --clean --if-exists, replacing every table.
     * On success the operator is signed out (their session row was just
     * dropped and recreated) and bounced to the login screen.
     */
    public function restore(Request $request, BackupManager $manager): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $validator = Validator::make($request->all(), [
            'restore_file' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'restore_confirmation' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.backups')->with('backups.error', $validator->errors()->first());
        }

        if (strtoupper(trim((string) $request->input('restore_confirmation'))) !== 'RESTORE') {
            return redirect()->route('settings.backups')->with(
                'backups.error',
                __('Type RESTORE in capital letters to confirm.'),
            );
        }

        $upload = $request->file('restore_file');
        $storedPath = $upload->store('backup-uploads', 'local');
        $absolute = storage_path('app/private/'.$storedPath);

        try {
            Log::warning('lodgely.backup.restore_started', [
                'user_id' => $request->user()->id,
                'original_name' => $upload->getClientOriginalName(),
            ]);

            $ignoredErrors = $manager->restore($absolute);

            Log::warning('lodgely.backup.restore_finished', [
                'user_id' => $request->user()->id,
                'ignored_errors' => $ignoredErrors,
            ]);

            // The dump just dropped and recreated every table, including
            // sessions — the current session is no longer valid. Send the
            // operator back to the login screen rather than rendering
            // against a connection that thinks it's still authenticated.
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Flash *after* invalidate/regenerate so these land in the fresh
            // session the login page reads. The integration warning matters
            // because secrets/tokens are encrypted with this server's
            // APP_KEY: a backup restored onto a different server (or after
            // APP_KEY rotation) can't decrypt them, so they read as empty
            // and have to be re-entered.
            $request->session()->flash('status', $ignoredErrors > 0
                ? __('Restore complete (:count statement(s) skipped — usually harmless objects that did not exist yet). Please sign in again.', ['count' => $ignoredErrors])
                : __('Restore complete. Please sign in again.'));
            $request->session()->flash('warning', __('Heads up: integration credentials (Google Sheets, Google Ads, Meta, AI keys) are stored encrypted with this server APP_KEY. If the backup came from a different server, you will need to re-enter and re-verify them under Settings before those integrations work again.'));

            return redirect()->route('login');
        } catch (Throwable $e) {
            Log::error('lodgely.backup.restore_failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return redirect()->route('settings.backups')->with(
                'backups.error',
                __('Restore failed: :error', ['error' => $e->getMessage()]),
            );
        } finally {
            @unlink($absolute);
        }
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
}
