<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Exceptions\MessagingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files staff attach to a reply. eBay does not take file uploads: it downloads each file from an
 * HTTPS URL we give it. So a file is kept on the private disk under an unguessable random folder and
 * served by the public `messages.file` route, which only works for that exact link.
 *
 * Stored attachment shape (also kept on the message so a failed send can be retried):
 *   ['name' => original file name, 'type' => IMAGE|PDF|DOC|TXT, 'url' => https link, 'local_path' => disk path, 'size' => bytes]
 */
class MessageAttachments
{
    public const MAX_FILES = 5;
    public const MAX_KB = 5120;
    public const DISK = 'local';
    public const DIR = 'message-attachments';

    /** extension => [eBay media type, content type, shown inline in the browser] */
    public const TYPES = [
        'jpg' => ['IMAGE', 'image/jpeg', true],
        'jpeg' => ['IMAGE', 'image/jpeg', true],
        'png' => ['IMAGE', 'image/png', true],
        'gif' => ['IMAGE', 'image/gif', true],
        'pdf' => ['PDF', 'application/pdf', true],
        'doc' => ['DOC', 'application/msword', false],
        'docx' => ['DOC', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', false],
        'txt' => ['TXT', 'text/plain; charset=UTF-8', true],
    ];

    /**
     * @return array<int,string>
     */
    public static function allowedExtensions(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * eBay must be able to download the file, which it only does over HTTPS.
     *
     * @throws MessagingException
     */
    public function assertDeliverable(): void
    {
        if (!str_starts_with(strtolower((string) url('/')), 'https://')) {
            throw new MessagingException('Attachments need this site to be served over HTTPS (eBay downloads each file from a secure link). Send the message without attachments, or set APP_URL to the https address.');
        }
    }

    /**
     * @param array<int,UploadedFile> $files
     * @return array<int,array<string,mixed>>
     *
     * @throws MessagingException
     */
    public function store(array $files): array
    {
        if (count($files) > self::MAX_FILES) {
            throw new MessagingException('You can attach at most ' . self::MAX_FILES . ' files to one message.');
        }

        $stored = [];

        try {
            foreach ($files as $file) {
                $ext = strtolower((string) $file->getClientOriginalExtension());
                if (!isset(self::TYPES[$ext]) || !$file->isValid() || $file->getSize() > self::MAX_KB * 1024) {
                    throw new MessagingException('Attachments can be images, PDF, Word or text files up to ' . (self::MAX_KB / 1024) . ' MB.');
                }

                $token = Str::random(40);
                $base = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));
                $name = Str::limit($base !== '' ? $base : 'file', 60, '') . '.' . $ext;
                $path = self::DIR . '/' . $token . '/' . $name;

                Storage::disk(self::DISK)->putFileAs(self::DIR . '/' . $token, $file, $name);

                $stored[] = [
                    'name' => Str::limit((string) $file->getClientOriginalName(), 95, ''),
                    'type' => self::TYPES[$ext][0],
                    'url' => route('messages.file', ['token' => $token, 'name' => $name]),
                    'local_path' => $path,
                    'size' => (int) $file->getSize(),
                ];
            }
        } catch (\Throwable $e) {
            $this->delete($stored); // all or nothing
            throw $e instanceof MessagingException ? $e : new MessagingException('The attachments could not be saved: ' . $e->getMessage(), previous: $e);
        }

        return $stored;
    }

    /**
     * Remove stored files (and their folders) for attachments carrying a local_path.
     *
     * @param array<int,array<string,mixed>>|null $attachments
     */
    public function delete(?array $attachments): void
    {
        foreach ($attachments ?? [] as $a) {
            $path = is_array($a) ? ($a['local_path'] ?? null) : null;
            if (is_string($path) && str_starts_with($path, self::DIR . '/') && !str_contains($path, '..')) {
                Storage::disk(self::DISK)->deleteDirectory(dirname($path));
            }
        }
    }

    /**
     * What the driver needs: the public link, name and media type only (never the local path).
     *
     * @param array<int,array<string,mixed>>|null $attachments
     * @return array<int,array{name:string,type:string,url:string}>
     */
    public function forDriver(?array $attachments): array
    {
        return array_values(array_map(
            fn (array $a) => ['name' => (string) ($a['name'] ?? 'file'), 'type' => (string) ($a['type'] ?? ''), 'url' => (string) ($a['url'] ?? '')],
            array_filter($attachments ?? [], fn ($a) => is_array($a) && !empty($a['url'])),
        ));
    }

    /**
     * What the browser may see: name, type and link only (never the disk path).
     *
     * @param array<int,array<string,mixed>>|null $attachments
     * @return array<int,array{name:?string,type:?string,url:?string}>
     */
    public function forDisplay(?array $attachments): array
    {
        return array_values(array_map(
            fn ($a) => ['name' => $a['name'] ?? null, 'type' => $a['type'] ?? null, 'url' => $a['url'] ?? null],
            array_filter($attachments ?? [], 'is_array'),
        ));
    }

    /**
     * Resolve a download request to a real file, or null. Strictly validated: the random folder and
     * the file name must match what store() produces.
     *
     * @return array{path:string, content_type:string, inline:bool, name:string}|null
     */
    public function locate(string $token, string $name): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{40}$/', $token) || !preg_match('/^[a-z0-9][a-z0-9-]*\.[a-z0-9]{2,4}$/', $name)) {
            return null;
        }

        $ext = substr(strrchr($name, '.'), 1);
        if (!isset(self::TYPES[$ext])) {
            return null;
        }

        $relative = self::DIR . '/' . $token . '/' . $name;
        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($relative)) {
            return null;
        }

        return ['path' => $disk->path($relative), 'content_type' => self::TYPES[$ext][1], 'inline' => self::TYPES[$ext][2], 'name' => $name];
    }
}
