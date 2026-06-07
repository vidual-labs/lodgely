<?php

namespace App\Http\Controllers;

use App\Support\Backup\BackupManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, BackupManager $manager, string $filename): BinaryFileResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $path = $manager->absolutePath($filename);

        abort_unless(file_exists($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
