<?php

use Digitonic\Mediatonic\Filament\Concerns\UsesMediatonic;
use Digitonic\Mediatonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Model;

// Define an ad-hoc model class for testing
class ArticleForTest extends Model
{
    use UsesMediatonic;

    protected $guarded = [];

    protected $table = 'articles';
}

beforeEach(function () {
    // create tables
    Illuminate\Support\Facades\Schema::create('articles', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->timestamps();
    });

    Illuminate\Support\Facades\Schema::create(get_mediatonic_table_name(), function ($table) {
        $table->id();
        $table->string('asset_uuid', 36)->nullable()->index();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->string('filename');
        $table->json('config')->nullable();
        $table->timestamps();
    });
});

it('can attach and retrieve media via trait convenience method', function () {
    $article = ArticleForTest::create(['title' => 'Hello']);

    $media = $article->addMediatonicMedia('file.jpg');

    expect($media)->toBeInstanceOf(Media::class)
        ->and($article->mediatonicMedia)->not()->toBeNull()
        ->and($article->mediatonicMedia->filename)->toBe('file.jpg');
});

it('removes media by id', function () {
    $article = ArticleForTest::create(['title' => 'Hello']);
    $media = $article->addMediatonicMedia('file.jpg');

    $result = $article->removeMediatonicMedia($media->id);

    expect($result)->toBeTrue()
        ->and($article->mediatonicMedia()->exists())->toBeFalse();
});
