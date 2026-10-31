<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores profile and group photos on the public disk.
 *
 * Security: the extension written to disk comes from the SNIFFED bytes (Symfony's MIME
 * guesser), never from the client file name, and the FormRequest has already limited the
 * type to jpeg/png/webp — so script-bearing formats (SVG, HTML) can never land under an
 * image name. File names are random, so a URL cannot be guessed from a user id. The client
 * is expected to downscale before upload (512 px); the server caps the size at 2 MB.
 */
final class Photos
{
    public const DISK = 'public';

    public const AVATARS_DIR = 'avatars';

    public const GROUPS_DIR = 'groups';

    /** Saves the upload and returns its relative path; the caller persists the path. */
    public function store(UploadedFile $file, string $dir): string
    {
        $extension = match ($file->guessExtension()) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $name = Str::lower(Str::random(40)).'.'.$extension;
        $file->storeAs($dir, $name, self::DISK);

        return $dir.'/'.$name;
    }

    /** Replaces an existing photo: stores the new one, then removes the old file. */
    public function replace(UploadedFile $file, string $dir, ?string $previous): string
    {
        $path = $this->store($file, $dir);
        $this->remove($previous);

        return $path;
    }

    public function remove(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function urlFor(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk(self::DISK)->url($path);
    }
}
