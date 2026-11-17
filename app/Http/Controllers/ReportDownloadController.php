<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDownloadController extends Controller
{
    public function __invoke(Request $request, int $id): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ReportExport $export */
        $export = ReportExport::findOrFail($id);

        // Verify user has active membership in the export's organization
        $hasAccess = Membership::query()
            ->where('organization_id', $export->organization_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized. You do not have access to reports from this organization.');
        }

        if ($export->status !== 'completed' || ! $export->file_path || ! Storage::disk('local')->exists($export->file_path)) {
            abort(404, 'Report export file not found or still processing.');
        }

        $filename = basename($export->file_path);

        return Storage::disk('local')->download($export->file_path, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
