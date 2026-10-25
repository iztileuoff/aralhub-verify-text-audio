<?php

use App\Models\CallRecording;
use Aws\Command;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('calls');
    config(['filesystems.disks.calls.bucket' => 'aralhub-calls']);
});

/**
 * A relative signed URL, the way the page and the command build them.
 */
function uploadUrl(string $route, string $batch = 'batch-1', ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute($route, $expiresAt ?? now()->addDay(), ['batch' => $batch], absolute: false);
}

/**
 * Swap the calls disk for one whose S3 client is a mock (multipart is not
 * something the fake local disk can do).
 */
function mockCallsBucket(): MockInterface
{
    $client = Mockery::mock(S3Client::class);
    $adapter = Mockery::mock(AwsS3V3Adapter::class);
    $adapter->shouldReceive('getClient')->andReturn($client);
    Storage::set('calls', $adapter);

    return $client;
}

describe('the upload page', function () {
    it('opens with a valid link and carries signed endpoints for the same batch', function () {
        $response = $this->get(uploadUrl('calls.upload.page'))
            ->assertSuccessful()
            ->assertSee('Загрузка звонков')
            ->assertSee('batch-1');

        expect($response->getContent())
            ->toContain('\/api\/v1\/calls-upload\/batch-1\/files?expires=')
            ->toContain('\/api\/v1\/calls-upload\/batch-1\/archive\/start?expires=');
    });

    it('refuses a link without a valid signature', function (string $url) {
        $this->get($url)->assertForbidden();
    })->with([
        'no signature' => fn () => '/calls-upload/batch-1',
        'another batch' => fn () => str_replace('batch-1', 'batch-2', uploadUrl('calls.upload.page')),
        'expired' => fn () => uploadUrl('calls.upload.page', expiresAt: now()->subMinute()),
    ]);

    it('is what calls:upload-link prints', function () {
        Artisan::call('calls:upload-link', ['batch' => 'batch-7', '--days' => 3]);
        $link = trim(explode("\n", Artisan::output())[0]);

        expect($link)->toStartWith(rtrim(config('app.url'), '/').'/calls-upload/batch-7?expires=');

        $this->get(substr($link, strlen(rtrim(config('app.url'), '/'))))->assertSuccessful();
    });

    it('validates the batch name and the lifetime', function (array $arguments) {
        $this->artisan('calls:upload-link', $arguments)->assertFailed();
    })->with([
        'bad batch' => [['batch' => '../x']],
        'too long' => [['batch' => 'b1', '--days' => 31]],
    ]);
});

describe('signing audio files', function () {
    it('signs a PUT url per file inside the batch folder', function () {
        $this->postJson(uploadUrl('calls.upload.files'), ['files' => [
            ['path' => 'calls/oct/0001.mp3', 'size' => 1000],
            ['path' => 'calls\\oct\\Жалоба 2.WAV', 'size' => 2000],
        ]])
            ->assertSuccessful()
            ->assertJsonPath('uploads.0.path', 'calls/oct/0001.mp3')
            ->assertJsonPath('uploads.1.path', 'calls/oct/Жалоба 2.WAV')
            ->assertJsonPath('uploads.0.headers', []);

        expect($this->postJson(uploadUrl('calls.upload.files'), ['files' => [['path' => 'a.mp3', 'size' => 1]]])->json('uploads.0.url'))
            ->toContain('source/batch-1/a.mp3');
    });

    it('refuses paths and files that are not audio of a sane size', function (array $file, string $field) {
        $this->postJson(uploadUrl('calls.upload.files'), ['files' => [$file]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'climbing out' => [['path' => 'calls/../../secret.mp3', 'size' => 10], 'files.0.path'],
        'not audio' => [['path' => 'calls/run.exe', 'size' => 10], 'files.0.path'],
        'empty segment' => [['path' => 'calls//a.mp3', 'size' => 10], 'files.0.path'],
        'too big' => [['path' => 'a.mp3', 'size' => CallRecording::UPLOAD_MAX_FILE_BYTES + 1], 'files.0.size'],
        'empty' => [['path' => 'a.mp3', 'size' => 0], 'files.0.size'],
    ]);

    it('only signs for the batch in the link', function () {
        $url = str_replace('batch-1', 'other', uploadUrl('calls.upload.files'));

        $this->postJson($url, ['files' => [['path' => 'a.mp3', 'size' => 1]]])->assertForbidden();
    });

    it('lists what the batch already holds so a reopened link resumes', function () {
        Storage::disk('calls')->put('source/batch-1/calls/a.mp3', '12345');
        Storage::disk('calls')->put('archives/batch-1/calls.rar', '1234567');
        Storage::disk('calls')->put('source/batch-2/b.mp3', 'x');

        $this->postJson(uploadUrl('calls.upload.existing'))
            ->assertSuccessful()
            ->assertExactJson(['files' => ['calls/a.mp3' => 5], 'archives' => ['calls.rar' => 7]]);

        $this->postJson(uploadUrl('calls.upload.existing', 'empty-batch'))
            ->assertSuccessful()
            ->assertExactJson(['files' => [], 'archives' => []]);
    });
});

describe('uploading an archive in parts', function () {
    beforeEach(function () {
        $this->partSize = CallRecording::UPLOAD_PART_BYTES;
        $this->size = 2 * $this->partSize + 1000;
    });

    it('starts a new multipart upload', function () {
        $client = mockCallsBucket();
        $client->shouldReceive('listMultipartUploads')->once()->andReturn(new Result(['Uploads' => []]));
        $client->shouldReceive('createMultipartUpload')->once()
            ->withArgs(fn (array $args): bool => $args['Bucket'] === 'aralhub-calls' && $args['Key'] === 'archives/batch-1/calls.rar')
            ->andReturn(new Result(['UploadId' => 'U1']));

        $this->postJson(uploadUrl('calls.upload.archive.start'), ['name' => 'calls.rar', 'size' => $this->size])
            ->assertSuccessful()
            ->assertExactJson(['upload_id' => 'U1', 'part_size' => $this->partSize, 'parts_total' => 3, 'uploaded' => []]);
    });

    it('resumes an unfinished upload, keeping only parts of the right size', function () {
        $client = mockCallsBucket();
        $client->shouldReceive('listMultipartUploads')->andReturn(new Result(['Uploads' => [
            ['Key' => 'archives/batch-1/calls.rar.old', 'UploadId' => 'OTHER', 'Initiated' => '2026-10-24T09:00:00Z'],
            ['Key' => 'archives/batch-1/calls.rar', 'UploadId' => 'U-OLD', 'Initiated' => '2026-10-24T08:00:00Z'],
            ['Key' => 'archives/batch-1/calls.rar', 'UploadId' => 'U-NEW', 'Initiated' => '2026-10-24T10:00:00Z'],
        ]]));
        $client->shouldNotReceive('createMultipartUpload');
        $client->shouldReceive('listParts')
            ->withArgs(fn (array $args): bool => $args['UploadId'] === 'U-NEW')
            ->andReturn(new Result(['Parts' => [
                ['PartNumber' => 1, 'Size' => $this->partSize, 'ETag' => '"a"'],
                ['PartNumber' => 2, 'Size' => 5, 'ETag' => '"broken"'],
                ['PartNumber' => 3, 'Size' => 1000, 'ETag' => '"c"'],
            ], 'IsTruncated' => false]));

        $this->postJson(uploadUrl('calls.upload.archive.start'), ['name' => 'calls.rar', 'size' => $this->size])
            ->assertSuccessful()
            ->assertJsonPath('upload_id', 'U-NEW')
            ->assertJsonPath('uploaded', [1, 3]);
    });

    it('signs the requested parts', function () {
        $client = mockCallsBucket();
        $client->shouldReceive('getCommand')->twice()
            ->withArgs(fn (string $name, array $args): bool => $name === 'UploadPart' && $args['UploadId'] === 'U1' && $args['Key'] === 'archives/batch-1/calls.rar')
            ->andReturnUsing(fn (string $name, array $args): Command => new Command($name, $args));
        $client->shouldReceive('createPresignedRequest')->twice()
            ->andReturnUsing(fn (Command $command): Request => new Request('PUT', 'https://r2.test/archives/batch-1/calls.rar?partNumber='.$command['PartNumber'].'&X-Amz-Signature=s'));

        $this->postJson(uploadUrl('calls.upload.archive.parts'), ['name' => 'calls.rar', 'size' => $this->size, 'upload_id' => 'U1', 'parts' => [1, 3]])
            ->assertSuccessful()
            ->assertJsonPath('urls.1', 'https://r2.test/archives/batch-1/calls.rar?partNumber=1&X-Amz-Signature=s')
            ->assertJsonPath('urls.3', 'https://r2.test/archives/batch-1/calls.rar?partNumber=3&X-Amz-Signature=s');
    });

    it('does not sign parts beyond the end of the file', function () {
        mockCallsBucket();

        $this->postJson(uploadUrl('calls.upload.archive.parts'), ['name' => 'calls.rar', 'size' => $this->size, 'upload_id' => 'U1', 'parts' => [4]])
            ->assertUnprocessable();
    });

    it('assembles the archive only when every part is there', function () {
        $client = mockCallsBucket();
        $client->shouldReceive('listParts')->andReturn(new Result(['Parts' => [
            ['PartNumber' => 1, 'Size' => $this->partSize, 'ETag' => '"a"'],
            ['PartNumber' => 3, 'Size' => 1000, 'ETag' => '"c"'],
        ], 'IsTruncated' => false]));
        $client->shouldNotReceive('completeMultipartUpload');

        $this->postJson(uploadUrl('calls.upload.archive.complete'), ['name' => 'calls.rar', 'size' => $this->size, 'upload_id' => 'U1'])
            ->assertUnprocessable()
            ->assertJsonPath('missing', [2]);
    });

    it('assembles the archive from the parts the bucket holds', function () {
        $client = mockCallsBucket();
        $client->shouldReceive('listParts')->andReturn(new Result(['Parts' => [
            ['PartNumber' => 1, 'Size' => $this->partSize, 'ETag' => '"a"'],
            ['PartNumber' => 2, 'Size' => $this->partSize, 'ETag' => '"b"'],
            ['PartNumber' => 3, 'Size' => 1000, 'ETag' => '"c"'],
        ], 'IsTruncated' => false]));
        $client->shouldReceive('completeMultipartUpload')->once()
            ->withArgs(fn (array $args): bool => $args['Key'] === 'archives/batch-1/calls.rar'
                && $args['UploadId'] === 'U1'
                && $args['MultipartUpload']['Parts'] === [
                    ['PartNumber' => 1, 'ETag' => '"a"'],
                    ['PartNumber' => 2, 'ETag' => '"b"'],
                    ['PartNumber' => 3, 'ETag' => '"c"'],
                ])
            ->andReturn(new Result([]));

        $this->postJson(uploadUrl('calls.upload.archive.complete'), ['name' => 'calls.rar', 'size' => $this->size, 'upload_id' => 'U1'])
            ->assertSuccessful()
            ->assertExactJson(['complete' => true]);
    });

    it('accepts archive volumes and refuses anything else', function (string $name, bool $accepted) {
        $client = mockCallsBucket();
        $client->shouldReceive('listMultipartUploads')->andReturn(new Result(['Uploads' => []]));
        $client->shouldReceive('createMultipartUpload')->andReturn(new Result(['UploadId' => 'U1']));

        $response = $this->postJson(uploadUrl('calls.upload.archive.start'), ['name' => $name, 'size' => 10]);

        $accepted ? $response->assertSuccessful() : $response->assertJsonValidationErrors('name');
    })->with([
        ['calls.zip', true],
        ['calls.7z', true],
        ['calls.part2.rar', true],
        ['calls.r01', true],
        ['calls.7z.003', true],
        ['calls.mp3', false],
        ['../calls.rar', false],
        ['sub/calls.rar', false],
    ]);
});

describe('server-side commands', function () {
    beforeEach(function () {
        $this->dir = storage_path('framework/testing/calls-import-'.uniqid());
        mkdir($this->dir.'/oct', 0755, true);
        file_put_contents($this->dir.'/oct/a.mp3', '12345');
        file_put_contents($this->dir.'/b.wav', '1234567');
        file_put_contents($this->dir.'/readme.txt', 'x');
    });

    afterEach(function () {
        File::deleteDirectory($this->dir);
    });

    it('uploads an unpacked folder and registers the calls, skipping what is already there', function () {
        $this->artisan('calls:upload', ['dir' => $this->dir, '--batch' => 'batch-1'])
            ->expectsOutputToContain('Uploaded 2, already there 0, failed 0; other files ignored: 1.')
            ->expectsOutputToContain('registered 2 new calls (batch: batch-1)')
            ->assertSuccessful();

        Storage::disk('calls')->assertExists(['source/batch-1/oct/a.mp3', 'source/batch-1/b.wav']);

        expect(CallRecording::query()->pluck('source_path')->sort()->values()->all())
            ->toBe(['source/batch-1/b.wav', 'source/batch-1/oct/a.mp3']);

        $this->artisan('calls:upload', ['dir' => $this->dir, '--batch' => 'batch-1'])
            ->expectsOutputToContain('Uploaded 0, already there 2')
            ->assertSuccessful();

        expect(CallRecording::query()->count())->toBe(2);
    });

    it('keeps the customer\'s names of files uploaded under neutral ones', function () {
        $mapping = $this->dir.'/../originals-'.uniqid().'.tsv';
        file_put_contents($mapping, "oct/a.mp3\tTulpor/2026-10-01/00-00-03_in_+998991301906_ZUXRA_N1.mp3\nb.wav\tTulpor/2026-10-02/x.wav\nmissing.mp3\tnope.mp3\n");

        $this->artisan('calls:upload', ['dir' => $this->dir, '--batch' => 'batch-1', '--originals' => $mapping])
            ->expectsOutputToContain('Original names stored for 2 calls.')
            ->assertSuccessful();

        expect(CallRecording::query()->where('source_path', 'source/batch-1/oct/a.mp3')->value('original_filename'))
            ->toBe('Tulpor/2026-10-01/00-00-03_in_+998991301906_ZUXRA_N1.mp3');

        unlink($mapping);
    });

    it('fails clearly when the names file cannot be read', function () {
        $this->artisan('calls:upload', ['dir' => $this->dir, '--batch' => 'batch-1', '--originals' => '/no/such.tsv'])
            ->expectsOutputToContain('Cannot read /no/such.tsv.')
            ->assertFailed();
    });

    it('downloads the uploaded archives to unpack them', function () {
        Storage::disk('calls')->put('archives/batch-1/calls.part1.rar', 'part-one');
        Storage::disk('calls')->put('archives/batch-1/calls.part2.rar', 'part-two!');
        $target = $this->dir.'/fetched';

        $this->artisan('calls:fetch-archives', ['batch' => 'batch-1', '--to' => $target])
            ->expectsOutputToContain('Downloaded 2 archive file(s)')
            ->assertSuccessful();

        expect(file_get_contents($target.'/calls.part1.rar'))->toBe('part-one')
            ->and(file_get_contents($target.'/calls.part2.rar'))->toBe('part-two!');

        $this->artisan('calls:fetch-archives', ['batch' => 'batch-1', '--to' => $target])
            ->expectsOutputToContain('Downloaded 0 archive file(s)')
            ->assertSuccessful();
    });
});
