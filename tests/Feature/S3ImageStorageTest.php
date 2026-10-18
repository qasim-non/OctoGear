<?php

namespace Tests\Feature;

use App\Services\ImageStorageService;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class S3ImageStorageTest extends TestCase
{
    public function test_real_s3_adapter_uses_private_bucket_settings_and_recorded_disks_for_read_copy_and_delete(): void
    {
        $objects = [];
        $writes = [];
        config([
            'images.disk' => 'images_s3',
            'filesystems.disks.images_s3.key' => 'test-key',
            'filesystems.disks.images_s3.secret' => 'test-secret',
            'filesystems.disks.images_s3.token' => null,
            'filesystems.disks.images_s3.region' => 'us-east-1',
            'filesystems.disks.images_s3.bucket' => 'private-test-bucket',
            'filesystems.disks.images_s3.root' => 'images',
            'filesystems.disks.images_s3.endpoint' => null,
            'filesystems.disks.images_s3.handler' => function (CommandInterface $command) use (&$objects, &$writes) {
                $key = $command['Key'];
                if ($command->getName() === 'PutObject') {
                    $writes[] = ['bucket' => $command['Bucket'], 'acl' => $command['ACL'], 'key' => $key];
                    $objects[$key] = (string) Utils::streamFor($command['Body']);

                    return Create::promiseFor(new Result(['ETag' => 'test-etag']));
                }
                if ($command->getName() === 'DeleteObject') {
                    unset($objects[$key]);

                    return Create::promiseFor(new Result);
                }
                if (! isset($objects[$key])) {
                    return Create::rejectionFor(new S3Exception('Not found', $command, ['code' => 'NotFound', 'status_code' => 404]));
                }

                return Create::promiseFor(new Result([
                    'ContentType' => 'image/png',
                    'ContentLength' => strlen($objects[$key]),
                    'Body' => Utils::streamFor($objects[$key]),
                ]));
            },
        ]);
        $service = $this->app->make(ImageStorageService::class);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a+1cAAAAASUVORK5CYII=');
        $tracked = [];
        $image = $service->store(UploadedFile::fake()->createWithContent('photo.png', $bytes), 'offers', $tracked);

        $this->assertSame('images_s3', $image['disk']);
        $this->assertSame('private-test-bucket', $writes[0]['bucket']);
        $this->assertSame('bucket-owner-full-control', $writes[0]['acl']);
        $this->assertSame('images/'.$image['path'], $writes[0]['key']);
        $this->assertSame($bytes, $objects[$writes[0]['key']]);

        // A changed default must not redirect reads/deletes of older records.
        Storage::fake('images_local');
        config(['images.disk' => 'images_local']);
        $response = $service->stream($image);
        $this->assertNotNull($response);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        ob_start();
        $response->sendContent();
        $this->assertSame($bytes, ob_get_clean());

        $copy = $service->copy($image, 'stores', $tracked);
        $this->assertSame('images_local', $copy['disk']);
        $this->assertSame($bytes, Storage::disk('images_local')->get($copy['path']));
        $service->delete($image);
        $this->assertSame([], $objects);
        Storage::disk('images_local')->assertExists($copy['path']);
    }
}
