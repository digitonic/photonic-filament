<?php

namespace Digitonic\Photonic\Filament\Forms\Components;

use Digitonic\Photonic\Filament\Enums\PresetEnum;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests\DeleteAsset;
use Digitonic\Photonic\Filament\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PhotonicImageField extends Group
{
    protected string $relationName = 'photonicMedia';

    /**
     * Field key used to mount the upload "dummy" state.
     * We do not dehydrate this field to DB; it only triggers the upload.
     */
    protected string $uploadFieldName = 'photonic_upload';

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
    protected string $previewClasses = 'rounded-xl max-w-full';

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
     * Reference to the PhotonicInput component for method forwarding.
     */
    protected ?PhotonicInput $inputComponent = null;

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
        // Create the input component and store reference
        $this->inputComponent = PhotonicInput::make($this->uploadFieldName)
            ->label('Upload Your Media')
            ->helperText('Media will be uploaded to Photonic')
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
                    $viewName = 'photonic-filament::components.image';

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
                Action::make('removePhotonicImage')
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
     * In this mode, the media ID is stored in a field on the model (e.g., in a JSON column),
     * rather than using a polymorphic relationship via model_type/model_id.
     */
    protected function buildIdModeSchema(): array
    {
        $mediaIdField = $this->mediaIdField;
        $mediaModelClass = config('photonic-filament.media_model', Media::class);

        $extractMediaId = function (mixed $value): ?int {
            if (is_null($value)) {
                return null;
            }

            if (is_int($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }

            // Handle array - extract first value
            if (is_array($value)) {
                $first = array_values($value)[0] ?? null;
                if (is_numeric($first)) {
                    return (int) $first;
                }

                return null;
            }

            return null;
        };

        // Helper to get media by ID
        /** @var callable(mixed): ?Media $getMediaById */
        $getMediaById = fn (mixed $value): ?Media => ($id = $extractMediaId($value)) ? $mediaModelClass::find($id) : null;
        $strRandom = md5($mediaIdField);

        // Create the input component - use a separate field name for uploads
        // The actual media ID will be stored via a Hidden field
        $uploadFieldName = $mediaIdField.'_upload';

        $this->inputComponent = PhotonicInput::make($mediaIdField)
            ->label('Upload Your Media')
            ->helperText('Media will be uploaded to Photonic')
            ->returnId()
            ->multiple(false)
            ->formatStateUsing(function ($state, Set $set) use ($mediaModelClass, $mediaIdField, $strRandom) {
                // We need to return back the FileName
                $media = $mediaModelClass::find($state);
                if ($media) {
                    $set($mediaIdField, $media->id);
                    $set('img_preview_id_mode_'.$strRandom, $media->id);

                    return $media->id;
                }

                return null;
            })
            ->dehydrateStateUsing(function ($state) use ($extractMediaId) {
                return $extractMediaId($state);
            })
            ->columnSpan(['sm' => 2]);

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        return [
            // Uploader - visible when no media ID exists
            // When visible, this component uploads and sets the media ID via afterStateUpdated
            $this->inputComponent,

            // Preview - visible when media ID exists
            TextEntry::make('img_preview_id_mode_'.$strRandom)
                ->label('Image Preview')
                // Hide this if the state is empty
                ->formatStateUsing(function (Get $get) use ($mediaIdField, $getMediaById) {
                    $media = $getMediaById($get($mediaIdField));

                    if (! $media) {
                        return 'No image available';
                    }

                    $filename = $media->filename ?? null;
                    if (! $filename) {
                        return 'No image available';
                    }

                    /** @var view-string $viewName */
                    $viewName = 'photonic-filament::components.image';

                    $html = view($viewName, [
                        'filename' => $filename,
                        'preset' => $this->previewPreset,
                        'class' => $this->previewClasses,
                        'alt' => $media->alt ?? $filename,
                        'media' => $media,
                    ])->render();

                    return new HtmlString($html);
                })
                ->columnSpanFull(),

            // Image Details Section - manually hydrate/dehydrate since we can't use ->relationship() in ID mode
            Section::make('Image Details')
                ->visible(function (Get $get) use ($mediaIdField, $extractMediaId): bool {
                    $mediaId = $extractMediaId($get($mediaIdField));

                    return ! is_null($mediaId);
                })
                ->schema([
                    TextInput::make('media_alt'.$strRandom)
                        ->label('Alt Text')
                        ->maxLength(255)
                        ->helperText('Alternative text for the image (for accessibility)')
                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                            $media = $getMediaById($get($mediaIdField));
                            $set('media_alt'.$strRandom, $media?->alt);
                        })
                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                            $media = $getMediaById($get($mediaIdField));
                            $media?->update(['alt' => $state]);

                            return null; // Don't save to parent model
                        })
                        ->columnSpan(['sm' => 2]),

                    TextInput::make('media_title'.$strRandom)
                        ->label('Title')
                        ->maxLength(255)
                        ->helperText('Title of the image')
                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                            $media = $getMediaById($get($mediaIdField));
                            $set('media_title'.$strRandom, $media?->title);
                        })
                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                            $media = $getMediaById($get($mediaIdField));
                            $media?->update(['title' => $state]);

                            return null;
                        })
                        ->columnSpan(['sm' => 2]),

                    Textarea::make('media_description'.$strRandom)
                        ->label('Description')
                        ->rows(3)
                        ->helperText('Detailed description of the image')
                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                            $media = $getMediaById($get($mediaIdField));
                            $set('media_description'.$strRandom, $media?->description);
                        })
                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                            $media = $getMediaById($get($mediaIdField));
                            $media?->update(['description' => $state]);

                            return null;
                        })
                        ->columnSpan(['sm' => 2]),

                    Textarea::make('media_caption'.$strRandom)
                        ->label('Caption')
                        ->rows(2)
                        ->helperText('Caption to display with the image')
                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                            $media = $getMediaById($get($mediaIdField));
                            $set('media_caption'.$strRandom, $media?->caption);
                        })
                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                            $media = $getMediaById($get($mediaIdField));
                            $media?->update(['caption' => $state]);

                            return null;
                        })
                        ->columnSpan(['sm' => 2]),
                ])
                ->columnSpanFull(),

            // Remove action
            Actions::make([
                Action::make('removePhotonicImage')
                    ->label(__('Remove Image'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (Get $get): bool => empty($get($mediaIdField)))
                    ->action(function (Get $get, $set, $livewire) use ($mediaIdField, $mediaModelClass, $extractMediaId) {
                        $mediaId = $extractMediaId($get($mediaIdField));
                        if ($mediaId) {
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
            ])
                ->columnSpanFull()
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
     * Forward method calls to the underlying PhotonicInput component.
     *
     * Example:
     *   PhotonicImageField::make('image')
     *       ->required();
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
