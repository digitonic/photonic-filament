<?php

namespace Digitonic\MediaTonic\Filament\Forms\Components;

use Digitonic\MediaTonic\Filament\Enums\PresetEnum;
use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\API;
use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\Requests\DeleteAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MediaTonicImageField extends Group
{
    protected string $relationName = 'mediaTonicMedia';

    /**
     * Field key used to mount the upload "dummy" state.
     * We do not dehydrate this field to DB; it only triggers the upload.
     */
    protected string $uploadFieldName = 'mediatonic_upload';

    /**
     * CDN preset path segment used for preview URL (e.g. 'original', 'featured').
     */
    protected string $previewPreset = PresetEnum::ORIGINAL->value;

    /**
     * Whether to show the remove action.
     */
    protected bool $showRemoveAction = true;

    /**
     * Additional CSS classes applied to the preview <img> tag.
     */
    protected string $previewClasses = 'rounded-xl max-w-full h-auto';

    /**
     * Whether to return the media ID instead of using polymorphic relationship.
     * When true, the field will store the media ID in the specified column.
     */
    protected bool $returnMediaId = false;

    /**
     * The field name to store/retrieve the media ID when in ID mode.
     */
    protected ?string $mediaIdField = null;

    /**
     * Reference to the MediatonicInput component for method forwarding.
     */
    protected ?MediatonicInput $inputComponent = null;

    /**
     * Callbacks to apply to the input component after it's created.
     * This stores method calls made before setUp() is called.
     */
    protected array $inputCallbacks = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Build inner schema during setup so it can react to configured properties.
        $this->schema($this->returnMediaId ? $this->buildIdModeSchema() : $this->buildRelationModeSchema());
    }

    /**
     * Build schema for relationship mode (original behavior).
     */
    protected function buildRelationModeSchema(): array
    {
        // Create the input componentUsesMediaTonic and store reference
        $this->inputComponent = MediaTonicInput::make($this->uploadFieldName)
            ->label('Upload Your Media')
            ->helperText('Media will be uploaded to MediaTonic')
            // Avoid saving uploader value to model
            ->dehydrated(false)
            // single file for this helper – users can still override by extending later if needed
            ->multiple(false)
            // Hide uploader if a record already has media via relation
            ->hidden(fn (?Model $record): bool => (bool) ($record->{$this->relationName} ?? null))
            // Ensure it records to media table by default so relation can exist
            ->columnSpan([
                'sm' => 2,
            ]);

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        return [
            // 1) Uploader - only visible when no related media exists.
            $this->inputComponent,

            // 2) Preview placeholder - only visible when relation exists
            TextEntry::make('img_preview'.'_preview_'.Str::random(2))
                ->label('Image Preview')
                ->hidden(fn (?Model $record): bool => ! ($record->{$this->relationName} ?? false))
                ->state(function (?Model $record) {
                    $media = $record->{$this->relationName} ?? null;
                    if (! $record || ! $media) {
                        return 'No image available';
                    }

                    $filename = $media->filename ?? null;
                    if (! $filename) {
                        return 'No image available';
                    }

                    /** @var view-string $viewName */
                    $viewName = 'mediatonic-filament::components.image';

                    $html = view($viewName, [
                        'filename' => $filename,
                        'preset' => $this->previewPreset,
                        'class' => $this->previewClasses,
                        'alt' => $media->alt ?? $filename,
                        'media' => $media,
                    ])->render();

                    return new HtmlString($html);
                })
                ->extraAttributes(['class' => 'prose'])
                ->columnSpanFull(),

            Section::make('Image Details')
                ->visible(fn (?Model $record): bool => ($record && $record->{$this->relationName}) ? ($record->{$this->relationName}->exists()) : false)
                ->live()
                ->relationship($this->relationName)
                ->schema([
                    // Metadata fields for new uploads
                    TextInput::make('alt')
                        ->label('Alt Text')
                        ->maxLength(255)
                        ->helperText('Alternative text for the image (for accessibility)')
                        ->columnSpan([
                            'sm' => 2,
                        ]),

                    TextInput::make('title')
                        ->label('Title')
                        ->maxLength(255)
                        ->helperText('Title of the image')
                        ->columnSpan([
                            'sm' => 2,
                        ]),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->helperText('Detailed description of the image')
                        ->columnSpan([
                            'sm' => 2,
                        ]),

                    Textarea::make('caption')
                        ->label('Caption')
                        ->rows(2)
                        ->helperText('Caption to display with the image')
                        ->columnSpan([
                            'sm' => 2,
                        ]),
                ]),

            // 3) Remove / replace action
            Actions::make([
                Action::make('removeMediatonicImage')
                    ->label(__('Remove Image'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (?Model $record): bool => ! ($record->{$this->relationName} ?? null))
                    ->action(function (?Model $record, $livewire) {
                        if ($record && $record->{$this->relationName}) {
                            // Send the API call to remove the asset
                            $api = new API;
                            $request = new DeleteAsset($record->{$this->relationName}->asset_uuid);
                            $response = $api->send($request);

                            if ($response->status() === 200) {
                                // Delete the related media record
                                $relation = $record->{$this->relationName}();
                                if (method_exists($relation, 'delete')) {
                                    $relation->delete();
                                } else {
                                    // If relation is already loaded as a model instance
                                    $record->{$this->relationName}->delete();
                                }

                                // Refresh the record and re-render
                                $record->refresh();
                                $livewire->dispatch('$refresh');
                            }
                        }
                    }),
            ])->columnSpanFull()
                ->visible($this->showRemoveAction),
        ];
    }

    /**
     * Build schema for ID mode (stores media ID directly).
     */
    protected function buildIdModeSchema(): array
    {
        $mediaIdField = $this->mediaIdField ?? $this->getStatePath();

        // Create the input component and store reference
        $this->inputComponent = MediaTonicInput::make($this->uploadFieldName)
            ->returnId()
            ->dehydrated(false)
            ->multiple(false)
            ->hidden(fn ($get) => filled($get($mediaIdField)))
            ->afterStateUpdated(function ($state, $set) use ($mediaIdField) {
                // When upload completes, store the returned ID
                if ($state) {
                    $set($mediaIdField, $state);
                }
            })
            ->columnSpan(['sm' => 2]);

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        return [
            // Hidden field to store the media ID
            TextInput::make($mediaIdField)
                ->label('Media')
                ->hidden()
                ->dehydrated(),

            // Uploader - visible when no media ID exists
            $this->inputComponent,

            Section::make('Image Details')
                ->relationship($this->relationName)
                ->schema([

                    // Metadata fields for new uploads
                    TextInput::make('alt')
                        ->label('Alt Text')
                        ->maxLength(255)
                        ->helperText('Alternative text for the image (for accessibility)')
                        ->columnSpan(['sm' => 2]),

                    TextInput::make('title')
                        ->label('Title')
                        ->maxLength(255)
                        ->helperText('Title of the image')
                        ->columnSpan(['sm' => 2]),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->helperText('Detailed description of the image')
                        ->columnSpan(['sm' => 2]),

                    Textarea::make('caption')
                        ->label('Caption')
                        ->rows(2)
                        ->helperText('Caption to display with the image')
                        ->columnSpan(['sm' => 2]),
                ]),

            // Remove action
            Actions::make([
                Action::make('removeMediatonicImage')
                    ->label(__('Remove Image'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn ($get) => blank($get($mediaIdField)))
                    ->action(function ($get, $set, $livewire) use ($mediaIdField) {
                        $mediaId = $get($mediaIdField);
                        if ($mediaId) {
                            $mediaModelClass = config('mediatonic-filament.media_model', \Digitonic\MediaTonic\Filament\Models\Media::class);
                            $media = $mediaModelClass::find($mediaId);

                            if ($media) {
                                // Send the API call to remove the asset
                                $api = new API;
                                $request = new DeleteAsset($media->asset_uuid);
                                $response = $api->send($request);

                                if ($response->status() === 200) {
                                    $media->delete();
                                    $set($mediaIdField, null);
                                    $livewire->dispatch('$refresh');
                                }
                            }
                        }
                    }),
            ])->columnSpanFull()
                ->visible($this->showRemoveAction),
        ];
    }

    /**
     * Configure the field to return and store the media ID.
     * Useful for Filament Builder blocks or when you need a direct reference.
     */
    public function returnId(bool $return = true, ?string $fieldName = null): static
    {
        $this->returnMediaId = $return;
        $this->mediaIdField = $fieldName;

        return $this->refreshSchema();
    }

    /**
     * Set the Eloquent relation name used to detect and delete media.
     */
    public function relation(string $name): static
    {
        $this->relationName = $name;

        // Rebuild schema reflecting the new relation
        return $this->refreshSchema();
    }

    /**
     * Set the preview preset (CDN folder segment).
     */
    public function preset(string $preset): static
    {
        $this->previewPreset = $preset;

        return $this->refreshSchema();
    }

    /**
     * Override CSS classes applied to preview image.
     */
    public function previewClasses(string $classes): static
    {
        $this->previewClasses = $classes;

        return $this->refreshSchema();
    }

    /**
     * Toggle the remove action visibility.
     */
    public function deletable(bool $enabled = true): static
    {
        $this->showRemoveAction = $enabled;

        return $this->refreshSchema();
    }

    /**
     * Utility to force Filament to re-evaluate inner schema after property change.
     */
    protected function refreshSchema(): static
    {
        // Trigger setUp again by resetting schema. Filament will call setUp during hydration; here
        // we simply replace the schema now for builder-time changes.
        $this->schema([]);
        $this->setUp();

        return $this;
    }

    /**
     * Forward method calls to the underlying MediatonicInput component.
     *
     * This magic method allows any standard Filament form component method to be called
     * on MediatonicImageField, and it will be automatically forwarded to the internal
     * MediatonicInput upload component. This includes methods like:
     *
     * - ->visible() / ->hidden()
     * - ->required() / ->requiredIf() / ->requiredWith()
     * - ->disabled() / ->readonly()
     * - ->default() / ->placeholder()
     * - ->helperText() / ->hint() / ->hintIcon()
     * - And any other Filament form component method
     *
     * Example:
     *   MediatonicImageField::make('image')
     *       ->required()
     *       ->visible(fn($get) => $get('needs_image'))
     *       ->helperText('Upload a high-quality image');
     *
     * The method calls are either applied immediately if the component is set up,
     * or queued to be applied during the setUp() phase if called before initialization.
     */
    public function __call(string $method, array $parameters): mixed
    {
        // List of methods that should not be forwarded (handled by this class or parent)
        $reservedMethods = [
            'schema', 'columns', 'columnSpan', 'columnSpanFull',
            'returnId', 'relation', 'preset', 'previewClasses', 'deletable',
        ];

        // If it's a reserved method or the component is already set up, delegate to parent
        if (in_array($method, $reservedMethods, true)) {
            return parent::__call($method, $parameters);
        }

        // If the input component exists, call the method on it directly
        if ($this->inputComponent !== null) {
            $result = $this->inputComponent->{$method}(...$parameters);

            // If the method returns the input component (for chaining), return $this instead
            return $result === $this->inputComponent ? $this : $result;
        }

        // If setUp hasn't been called yet, queue the callback for later
        $this->inputCallbacks[] = function ($component) use ($method, $parameters) {
            $component->{$method}(...$parameters);
        };

        // Return $this for method chaining
        return $this;
    }
}
