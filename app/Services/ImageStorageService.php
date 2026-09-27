<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Stores private image files; feature services own authorization and records.
 */
class ImageStorageService
{
    public function __construct(private FilesystemFactory $filesystems) {}

    /**
     * Register the generated path before writing so a failed write can also be
     * cleaned up when the calling database transaction rolls back.
     *
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     * @return array{disk: string, path: string, mime_type: string|null, size_bytes: int|false}
     */
    public function store(UploadedFile $file, string $directory, array &$storedFiles, ?string $disk = null): array
    {
        $disk ??= config('images.disk');
        $mimeType = $file->getMimeType();
        $filename = Str::uuid().'.'.$this->extensionFor($mimeType);
        $path = trim($directory, '/').'/'.$filename;
        $storedFiles[] = ['disk' => $disk, 'path' => $path];

        $result = $this->filesystems->disk($disk)->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'private'],
        );

        if ($result === false) {
            throw new RuntimeException('Unable to store image.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize(),
        ];
    }

    /** @param  array<string, mixed>  $image */
    public function stream(array $image): ?StreamedResponse
    {
        if (! $this->hasFile($image)) {
            return null;
        }

        $storage = $this->filesystems->disk($image['disk']);

        if (! $storage->exists($image['path'])) {
            return null;
        }

        return $storage->response(
            $image['path'],
            'image.'.$this->extensionFor($image['mime_type'] ?? null),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /**
     * Keep the calling record intact when storage is unavailable, allowing a
     * safe retry without losing the only reference to the file.
     *
     * @param  array<string, mixed>  $image
     */
    public function delete(array $image): void
    {
        if (! $this->hasFile($image)) {
            return;
        }

        try {
            $storage = $this->filesystems->disk($image['disk']);

            if ($storage->exists($image['path']) && ! $storage->delete($image['path'])) {
                throw new RuntimeException('Image deletion returned false.');
            }
        } catch (Throwable) {
            // Neither a client response nor its exception should expose the
            // disk, path, or underlying storage credentials.
            throw new BusinessRuleException(
                message: __('auth.general.media_cleanup_unavailable'),
                messageKey: 'auth.general.media_cleanup_unavailable',
                statusCode: 503,
            );
        }
    }

    /** @param  array<int, array{disk: string, path: string}>  $storedFiles */
    public function cleanup(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            try {
                $this->delete($file);
            } catch (Throwable $exception) {
                report($exception);

                // Rollback has already removed the owning record, so retain
                // the file reference for the scheduled cleanup retry.
                try {
                    $this->queueDeletion($file);
                } catch (Throwable $queueException) {
                    report($queueException);
                }
            }
        }
    }

    /**
     * Call inside the transaction replacing image records. The old references
     * and their deletion jobs commit together; a rollback cancels both.
     *
     * @param  array<int, array<string, mixed>>  $images
     */
    public function deleteAfterCommit(array $images): void
    {
        $ids = [];

        foreach ($images as $image) {
            if ($id = $this->queueDeletion($image)) {
                $ids[] = $id;
            }
        }

        if ($ids !== []) {
            DB::afterCommit(function () use ($ids): void {
                try {
                    $this->deletePending($ids);
                } catch (Throwable $exception) {
                    // The replacement is already committed. An unavailable
                    // queue must not make its caller clean up the new image.
                    report($exception);
                }
            });
        }
    }

    public function retryPendingDeletions(): int
    {
        return $this->deletePending();
    }

    /** @param  array<string, mixed>  $image */
    private function queueDeletion(array $image): ?int
    {
        if (! $this->hasFile($image)) {
            return null;
        }

        $reference = ['disk' => $image['disk'], 'path' => $image['path']];
        DB::table('pending_image_deletions')->insertOrIgnore([
            ...$reference,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('pending_image_deletions')->where($reference)->value('id');
    }

    /** @param  array<int, int>|null  $ids */
    private function deletePending(?array $ids = null): int
    {
        $deleted = 0;

        DB::table('pending_image_deletions')
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('id')
            ->chunkById(100, function ($files) use (&$deleted): void {
                foreach ($files as $file) {
                    try {
                        $this->delete(['disk' => $file->disk, 'path' => $file->path]);
                        DB::table('pending_image_deletions')->where('id', $file->id)->delete();
                        $deleted++;
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });

        return $deleted;
    }

    /**
     * Give a new owner its own file so removing the source record cannot
     * invalidate another record's image. Copies use the current shared disk.
     *
     * @param  array<string, mixed>  $image
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     * @return array{disk: string, path: string, mime_type: mixed, size_bytes: mixed}
     */
    public function copy(array $image, string $directory, array &$storedFiles): array
    {
        if (! $this->hasFile($image)) {
            throw new RuntimeException('Unable to copy image.');
        }

        $source = $this->filesystems->disk($image['disk'])->readStream($image['path']);

        if (! is_resource($source)) {
            throw new RuntimeException('Unable to read image.');
        }

        try {
            $disk = config('images.disk');
            $path = trim($directory, '/').'/'.Str::uuid().'.'.$this->extensionFor($image['mime_type'] ?? null);
            $storedFiles[] = ['disk' => $disk, 'path' => $path];

            if (! $this->filesystems->disk($disk)->put($path, $source, ['visibility' => 'private'])) {
                throw new RuntimeException('Unable to copy image.');
            }

            return [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $image['mime_type'] ?? null,
                'size_bytes' => $image['size_bytes'] ?? null,
            ];
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }

    /** @param  array<string, mixed>  $image */
    private function hasFile(array $image): bool
    {
        return is_string($image['disk'] ?? null) && $image['disk'] !== ''
            && is_string($image['path'] ?? null) && $image['path'] !== '';
    }

    private function extensionFor(?string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'image',
        };
    }
}
