<?php

use Digitonic\Mediatonic\Filament\Forms\Components\MediatonicInput;
use Digitonic\Mediatonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DummyRecord extends Model
{
    protected $guarded = [];

    protected $table = 'dummy_records';
}

beforeEach(function () {
    Schema::create('dummy_records', function ($table) {
        $table->id();
        $table->timestamps();
    });

    Schema::create(get_mediatonic_table_name(), function ($table) {
        $table->id();
        $table->string('asset_uuid', 36)->nullable()->index();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->string('filename');
        $table->json('config')->nullable();
        $table->timestamps();
    });
});

it('records an upload when context available', function () {
    // Simulate config
    config()->set('mediatonic.endpoint', 'https://api.example.test');
    config()->set('mediatonic.response_key', 'original_filename');
    config()->set('mediatonic.record_uploads', true);

    // Fake livewire-like component context object
    $livewire = new class
    {
        public $record;

        public function __construct($record)
        {
            $this->record = $record;
        }

        public function getRecord()
        {
            return $this->record;
        }
    };

    $record = DummyRecord::create();
    $livewire->record = $record;

    // Build component
    $component = new MediatonicInput('test_upload');
    $component->model(DummyRecord::class);
    $component->livewire($livewire);

    // Mock TemporaryUploadedFile-like object
    $file = new class
    {
        public function getRealPath()
        {
            return __FILE__;
        }

        public function getMimeType()
        {
            return 'image/jpeg';
        }

        public function getClientOriginalExtension()
        {
            return 'jpg';
        }

        public function getSize()
        {
            return 1234;
        }

        public function hashName()
        {
            return 'hashname123.jpg';
        }
    };

    // We cannot easily trigger saveUploadedFileUsing closure without Filament internals;
    // Instead call protected recordUpload via reflection.

    $ref = new ReflectionClass($component);
    $method = $ref->getMethod('recordUpload');
    $method->setAccessible(true);

    $jsonResponse = [
        'uuid' => '11111111-2222-3333-4444-555555555555',
        'original_filename' => 'stored-name.jpg',
    ];

    $method->invoke($component, 'stored-name.jpg', [
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1234,
        'width' => null,
        'height' => null,
        'hash_name' => 'hashname123.jpg',
    ], $jsonResponse);

    $media = Media::query()->first();
    expect($media)->not()->toBeNull()
        ->and($media->asset_uuid)->toBe('11111111-2222-3333-4444-555555555555')
        ->and($media->filename)->toBe('stored-name.jpg')
        ->and($media->model_type)->toBe(DummyRecord::class)
        ->and($media->model_id)->toBe($record->id);
});

it('skips recording when no model context id available', function () {
    config()->set('mediatonic.record_uploads', true);
    $component = new MediatonicInput('test_upload');

    $ref = new ReflectionClass($component);
    $method = $ref->getMethod('recordUpload');
    $method->setAccessible(true);

    $method->invoke($component, 'file.jpg', [], ['uuid' => 'abc']);

    expect(Media::query()->count())->toBe(0);
});
