<?php

namespace Database\Seeders\Support;

use App\Services\ImageStorageService;
use App\Support\ImageRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class DemoImages
{
    private array $storedFiles = [];

    public function __construct(private ImageStorageService $images) {}

    public function manifest(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../assets/images/manifest.json'), true, flags: JSON_THROW_ON_ERROR)['assets'];
    }

    /** Fail before writing any demo data if assets are missing or invalid. */
    public function verify(): void
    {
        $assets = $this->manifest();
        if (count($assets) < 30 || count($assets) > 40) {
            throw new RuntimeException('The demo asset manifest must contain 30–40 images.');
        }

        foreach ($assets as $asset) {
            $path = $this->source($asset['file']);
            if (! is_file($path)) {
                throw new RuntimeException("Missing demo image: {$asset['file']}");
            }
            $validator = Validator::make(
                ['image' => new UploadedFile($path, $asset['file'], test: true)],
                ['image' => ImageRules::file()],
            );
            if ($validator->fails()) {
                throw new RuntimeException("Invalid demo image {$asset['file']}: ".$validator->errors()->first());
            }
        }
    }

    public function picture(string $table, string $foreignKey, int $ownerId, string $asset, int $sortOrder): void
    {
        $identity = [$foreignKey => $ownerId, 'sort_order' => $sortOrder];
        $existing = DB::table($table)->where($identity)->orderBy('id')->first();
        if ($existing?->deleted_at !== null) {
            return;
        }

        $metadata = $this->metadata(
            $existing ? (array) $existing : [],
            $asset,
            "demo/{$table}/{$ownerId}",
            $table === 'customer_car_pictures' ? config('customer_car_media.disk') : null,
        );

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($metadata);
        } else {
            SeedRecords::once($table, $identity, $metadata);
        }
    }

    public function attachment(string $table, int $id, string $prefix, string $asset): void
    {
        $record = (array) DB::table($table)->where('id', $id)->first();
        if (($record['deleted_at'] ?? null) !== null) {
            return;
        }
        $metadata = $this->metadata([
            'disk' => $record[$prefix.'disk'] ?? null,
            'path' => $record[$prefix.'path'] ?? null,
            'mime_type' => $record[$prefix.'mime_type'] ?? null,
            'size_bytes' => $record[$prefix.'size_bytes'] ?? null,
        ], $asset, "demo/{$table}/{$id}");

        $attributes = [];
        foreach ($metadata as $key => $value) {
            $attributes[$prefix.$key] = $value;
        }
        DB::table($table)->where('id', $id)->update($attributes);
    }

    public function rollback(): void
    {
        $this->images->cleanup($this->storedFiles);
        $this->storedFiles = [];
    }

    public function committed(): void
    {
        $this->storedFiles = [];
    }

    private function metadata(array $existing, string $asset, string $directory, ?string $disk = null): array
    {
        if (! empty($existing['disk']) && ! empty($existing['path'])
            && Storage::disk($existing['disk'])->exists($existing['path'])) {
            return array_intersect_key($existing, array_flip(['disk', 'path', 'mime_type', 'size_bytes']));
        }

        $file = new UploadedFile($this->source($asset), $asset, test: true);

        return $this->images->store($file, $directory, $this->storedFiles, $disk);
    }

    private function source(string $asset): string
    {
        if (basename($asset) !== $asset) {
            throw new RuntimeException('Demo asset names must not contain directories.');
        }

        return __DIR__.'/../assets/images/'.$asset;
    }
}
