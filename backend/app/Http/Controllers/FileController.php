<?php

namespace App\Http\Controllers;

use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Serve download file with signed URL.
     */
    public function serve(int $id, Request $request)
    {
        $download = Download::find($id);

        if (!$download) {
            abort(404, 'Download not found');
        }

        if ($download->status !== Download::STATUS_COMPLETED) {
            abort(400, 'Download not ready');
        }

        if (!$download->file_path) {
            abort(404, 'File not found');
        }

        // Verify token if provided (for signed URLs)
        $token = $request->get('token');
        if ($token) {
            $expectedToken = hash_hmac('sha256', "{$id}:3600", config('app.key'));
            if (!hash_equals($expectedToken, $token)) {
                abort(403, 'Invalid or expired token');
            }
        }

        // Check if file exists
        if (!Storage::exists($download->file_path)) {
            abort(404, 'File no longer available');
        }

        // Determine content type
        $extension = pathinfo($download->file_path, PATHINFO_EXTENSION);
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
        ];
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

        return Storage::response($download->file_path, $download->title . '.' . $extension)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'attachment; filename="' . urlencode($download->title . '.' . $extension) . '"');
    }
}
