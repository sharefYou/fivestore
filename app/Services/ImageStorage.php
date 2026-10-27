<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores catalog images under server-generated names, and removes files that
 * no record points at any more.
 */
class ImageStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('catalog.images.disk'));
    }

    /**
     * Persist an upload and return its path on the disk.
     *
     * The caller must have validated the upload first (see ImageRules); this
     * re-checks the decoded image because an extension proves nothing about
     * the bytes on disk.
     *
     * @param  'categories'|'products'|'site'  $bucket
     */
    public function store(UploadedFile $file, string $bucket): string
    {
        $directory = config("catalog.images.directories.{$bucket}");

        if (! is_string($directory)) {
            throw new RuntimeException("Unknown image bucket [{$bucket}].");
        }

        if (! $this->looksLikeAcceptedImage($file->getRealPath())) {
            throw new RuntimeException('The uploaded file is not a supported image.');
        }

        // The client's filename is never used: it is attacker-controlled and
        // could carry a second extension or a traversal sequence.
        $name = Str::uuid()->toString().'.'.$this->extensionFor($file->getRealPath());

        $path = $file->storeAs($directory, $name, ['disk' => config('catalog.images.disk')]);

        if ($path === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        return $path;
    }

    /**
     * Persist an image that already sits on the local filesystem, applying
     * exactly the same checks and naming as an upload.
     *
     * Used for images fetched from a remote source: by the time the bytes
     * reach here they are an ordinary local file, and they earn no more trust
     * than a file a browser sent.
     *
     * @param  'categories'|'products'|'site'  $bucket
     */
    public function storeFromPath(string $sourcePath, string $bucket): string
    {
        $directory = config("catalog.images.directories.{$bucket}");

        if (! is_string($directory)) {
            throw new RuntimeException("Unknown image bucket [{$bucket}].");
        }

        if (! $this->looksLikeAcceptedImage($sourcePath)) {
            throw new RuntimeException('The file is not a supported image.');
        }

        $contents = @file_get_contents($sourcePath);

        if ($contents === false) {
            throw new RuntimeException('The image could not be read.');
        }

        // The remote filename is never used, exactly as with an upload.
        $path = $directory.'/'.Str::uuid()->toString().'.'.$this->extensionFor($sourcePath);

        if (! $this->disk()->put($path, $contents)) {
            throw new RuntimeException('The image could not be stored.');
        }

        return $path;
    }

    /**
     * Remove a stored file, tolerating one that is already gone.
     */
    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        try {
            $this->disk()->delete($path);
        } catch (\Throwable $e) {
            // A missing or locked file must not break the surrounding write.
            Log::warning('Failed to delete catalog image.', [
                'path' => $path,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * True when the bytes decode as one of the accepted image types and the
     * dimensions are within the configured bounds.
     */
    public function looksLikeAcceptedImage(string|false $realPath): bool
    {
        if (! is_string($realPath) || ! is_file($realPath)) {
            return false;
        }

        $info = @getimagesize($realPath);

        if ($info === false) {
            return false;
        }

        [$width, $height] = $info;
        $mime = $info['mime'] ?? null;

        if (! in_array($mime, config('catalog.images.accepted_mime_types'), true)) {
            return false;
        }

        $min = (int) config('catalog.images.min_dimension');
        $max = (int) config('catalog.images.max_dimension');

        return $width >= $min && $height >= $min && $width <= $max && $height <= $max;
    }

    protected function extensionFor(string $realPath): string
    {
        $mime = @getimagesize($realPath)['mime'] ?? null;

        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
