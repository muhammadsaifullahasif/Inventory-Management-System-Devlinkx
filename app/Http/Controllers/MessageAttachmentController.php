<?php

namespace App\Http\Controllers;

use App\Services\Messaging\MessageAttachments;

/**
 * Public download link for files attached to a reply. eBay fetches each attachment from here, so this
 * route is deliberately outside the login. It only serves a file whose random 40-character folder and
 * exact name are in the URL; nothing can be listed or guessed.
 */
class MessageAttachmentController extends Controller
{
    public function show(MessageAttachments $attachments, string $token, string $name)
    {
        $file = $attachments->locate($token, $name);
        abort_unless($file, 404);

        return response()->file($file['path'], [
            'Content-Type' => $file['content_type'],
            'Content-Disposition' => ($file['inline'] ? 'inline' : 'attachment') . '; filename="' . $file['name'] . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
