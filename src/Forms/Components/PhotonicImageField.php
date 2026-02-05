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
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
            ->label('Upload Media')
            ->helperText('Select an image to upload to Photonic CDN')
            ->dehydrated(false) // Don't save uploader value to model
            ->multiple(false)
            ->acceptedFileTypes(['image/*'])
            ->default([]) // Always start with empty state
            ->afterStateHydrated(function ($component, $state) {
                // Prevent hydration of existing filenames - always start fresh
                $component->state([]);
            })
            ->columnSpanFull();

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        $hasMedia = fn (?Model $record): bool => (bool) ($record?->{$this->relationName} ?? null);

        return [
            Section::make('Photonic Media')
                // Check if the page we're on is a create page
                ->visible(fn (?Model $record): bool => $record?->exists ?? false)
                ->description('Manage your image upload and metadata')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Tabs::make('photonic_tabs')
                        ->tabs([
                            // Tab 1: Upload / Preview
                            Tabs\Tab::make('Media')
                                ->icon('heroicon-o-photo')
                                ->schema([
                                    // Show uploader when no media exists
                                    Group::make([
                                        $this->inputComponent,
                                    ])
                                        ->visible(fn (?Model $record): bool => ! $hasMedia($record))
                                        ->columnSpanFull(),

                                    // Show preview when media exists
                                    Group::make([
                                        TextEntry::make('img_preview_'.'_preview_'.Str::random(2))
                                            ->label('Current Image')
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
                                            ->extraAttributes(['class' => 'prose max-w-none'])
                                            ->columnSpanFull(),
                                    ])
                                        ->visible($hasMedia)
                                        ->columnSpanFull(),
                                ]),

                            // Tab 2: Image Details
                            Tabs\Tab::make('Details')
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            TextInput::make('alt')
                                                ->label('Alt Text')
                                                ->maxLength(255)
                                                ->helperText('Describe the image for accessibility (screen readers)')
                                                ->columnSpan(['sm' => 2]),

                                            TextInput::make('title')
                                                ->label('Title')
                                                ->maxLength(255)
                                                ->helperText('Image title')
                                                ->columnSpan(['sm' => 2]),

                                            Textarea::make('description')
                                                ->label('Description')
                                                ->rows(4)
                                                ->helperText('Detailed description of the image content')
                                                ->columnSpanFull(),

                                            Textarea::make('caption')
                                                ->label('Caption')
                                                ->rows(2)
                                                ->helperText('Caption to display with the image')
                                                ->columnSpanFull(),
                                        ])
                                        ->relationship($this->relationName)
                                        ->columnSpanFull(),
                                ]),

                            // Tab 3: Actions
                            Tabs\Tab::make('Actions')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->visible(fn (?Model $record): bool => $hasMedia($record) && $this->showRemoveAction)
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            Group::make([
                                                Actions::make([
                                                    Action::make('removePhotonicImage')
                                                        ->label('Delete Image')
                                                        ->color('danger')
                                                        ->icon('heroicon-o-trash')
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Delete Image')
                                                        ->modalDescription('Are you sure you want to delete this image? This action cannot be undone and will remove the image from the CDN.')
                                                        ->modalSubmitActionLabel('Yes, delete it')
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
                                                ])->columnSpanFull(),
                                            ])->columnSpanFull(),

                                            Group::make([
                                                TextEntry::make('info')
                                                    ->label('')
                                                    ->state('Deleting the image will permanently remove it from the CDN.')
                                                    ->color('gray')
                                                    ->columnSpanFull(),
                                            ])->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->columnSpanFull()
                        ->contained(false),
                ])
                ->columnSpanFull(),

            // Warning message around the page needing to be saved
            Section::make('Photonic Media')
                ->description('Manage your image upload and metadata')
                ->visible(fn (?Model $record): bool => ! $record?->exists ?? true)
                ->collapsible()
                ->collapsed()
                ->schema([
                    ViewField::make('info')
                        ->view('photonic-filament::components.warning', [
                            'slot' => 'You must save the page before uploading an image.',
                        ])
                        ->columnSpanFull(),
                ])
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

        // Use separate field names for upload vs storage to avoid conflicts
        // Upload field: handles file upload and returns media ID
        // Hidden field: stores the media ID in the model
        $uploadFieldName = $mediaIdField.'_upload';

        $uploadField = PhotonicInput::make($uploadFieldName)
            ->label('Upload Media')
            ->helperText('Select an image to upload to Photonic CDN. Save the form to complete the upload.')
            ->returnId()
            ->multiple(false)
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, Set $set) use ($mediaIdField) {
                // Copy media ID to hidden field when available
                if (is_numeric($state)) {
                    $set($mediaIdField, (int) $state);
                }
            })
            ->columnSpanFull();

        $this->inputComponent = $uploadField;

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        $hasMedia = function (Get $get) use ($mediaIdField): bool {
            $value = $get($mediaIdField);

            // Check for numeric media ID (not TemporaryUploadedFile objects)
            return is_numeric($value) && ! empty($value);
        };

        return [
            // Hidden field stores the media ID and syncs from upload field on save
            Hidden::make($mediaIdField)
                ->default(null)
                ->dehydrateStateUsing(function ($state, Get $get) use ($uploadFieldName) {
                    // Read media ID from upload field after it's been processed
                    $uploadValue = $get($uploadFieldName);

                    if (is_numeric($uploadValue) && ! empty($uploadValue)) {
                        return (int) $uploadValue;
                    }

                    return $state;
                }),

            Section::make('Photonic Media')
                ->description('Manage your image upload and metadata')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Tabs::make('photonic_tabs')
                        ->tabs([
                            // Tab 1: Upload / Preview
                            Tabs\Tab::make('Media')
                                ->icon('heroicon-o-photo')
                                ->schema([
                                    // Upload field - shown when no media exists
                                    Group::make([
                                        $this->inputComponent,
                                    ])
                                        ->hidden(fn (Get $get): bool => $hasMedia($get))
                                        ->live()
                                        ->columnSpanFull(),

                                    // Preview - shown when media exists
                                    Group::make([
                                        ViewField::make('livewire_preview_'.$strRandom)
                                            ->view('photonic-filament::livewire-wrapper')
                                            ->viewData(fn (Get $get) => [
                                                'mediaId' => $get($mediaIdField),
                                                'preset' => $this->previewPreset,
                                                'previewClasses' => $this->previewClasses,
                                                'fieldName' => $mediaIdField,
                                            ])
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ])
                                        ->visible($hasMedia)
                                        ->live()
                                        ->columnSpanFull(),
                                ]),

                            // Tab 2: Image Details
                            Tabs\Tab::make('Details')
                                ->icon('heroicon-o-document-text')
                                ->visible($hasMedia)
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            TextInput::make('media_alt'.$strRandom)
                                                ->label('Alt Text')
                                                ->maxLength(255)
                                                ->helperText('Describe the image for accessibility (screen readers)')
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
                                                ->helperText('Image title')
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
                                                ->rows(4)
                                                ->helperText('Detailed description of the image content')
                                                ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                                                    $media = $getMediaById($get($mediaIdField));
                                                    $set('media_description'.$strRandom, $media?->description);
                                                })
                                                ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                                                    $media = $getMediaById($get($mediaIdField));
                                                    $media?->update(['description' => $state]);

                                                    return null;
                                                })
                                                ->columnSpanFull(),

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
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                ]),

                            // Tab 3: Actions
                            Tabs\Tab::make('Actions')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->visible(fn (Get $get): bool => $hasMedia($get) && $this->showRemoveAction)
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            Group::make([
                                                Actions::make([
                                                    Action::make('removePhotonicImage_'.$strRandom) // Make action name unique per field
                                                        ->label('Delete Image')
                                                        ->color('danger')
                                                        ->icon('heroicon-o-trash')
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Delete Image')
                                                        ->modalDescription('Are you sure you want to delete this image? This action cannot be undone and will remove the image from the CDN.')
                                                        ->modalSubmitActionLabel('Yes, delete it')
                                                        ->action(function (Get $get, Set $set, $livewire) use ($mediaIdField, $mediaModelClass, $extractMediaId, $uploadFieldName) {
                                                            $mediaId = $extractMediaId($get($mediaIdField));

                                                            if (! $mediaId) {
                                                                return;
                                                            }

                                                            $media = $mediaModelClass::find($mediaId);

                                                            // Delete from CDN if media exists
                                                            if ($media) {
                                                                try {
                                                                    $api = new API;
                                                                    $request = new DeleteAsset($media->asset_uuid);
                                                                    $response = $api->send($request);

                                                                    if ($response->status() === 200) {
                                                                        $media->delete();
                                                                    }
                                                                } catch (\Exception $e) {
                                                                    // Silently continue to clear field even if API delete fails
                                                                }
                                                            }

                                                            // Update database immediately - clear the field reference
                                                            $record = null;

                                                            // Get the current record from Livewire component
                                                            if (method_exists($livewire, 'getRecord')) {
                                                                $record = $livewire->getRecord();
                                                            } elseif (property_exists($livewire, 'record')) {
                                                                $record = $livewire->record;
                                                            } elseif (property_exists($livewire, 'ownerRecord')) {
                                                                $record = $livewire->ownerRecord;
                                                            }

                                                            if ($record && method_exists($record, 'update')) {
                                                                // Handle JSON fields (e.g., content.0.data.hero_image)
                                                                if (str_contains($mediaIdField, '.')) {
                                                                    $parts = explode('.', $mediaIdField);
                                                                    $rootField = array_shift($parts);

                                                                    $jsonData = $record->{$rootField};

                                                                    if (is_string($jsonData)) {
                                                                        $jsonData = json_decode($jsonData, true);
                                                                    }

                                                                    // Navigate and set nested value to null
                                                                    $current = &$jsonData;
                                                                    foreach ($parts as $index => $part) {
                                                                        if ($index === count($parts) - 1) {
                                                                            $current[$part] = null;
                                                                        } else {
                                                                            if (! isset($current[$part])) {
                                                                                break;
                                                                            }
                                                                            $current = &$current[$part];
                                                                        }
                                                                    }

                                                                    $record->update([
                                                                        $rootField => $jsonData,
                                                                    ]);
                                                                } else {
                                                                    // Simple field - direct update
                                                                    $record->update([
                                                                        $mediaIdField => null,
                                                                    ]);
                                                                }
                                                            }

                                                            // Clear field state to trigger visibility changes
                                                            $set($mediaIdField, null);
                                                            $set($uploadFieldName, null);
                                                        }),
                                                ])->columnSpanFull(),
                                            ])->columnSpanFull(),

                                            Group::make([
                                                TextEntry::make('info')
                                                    ->label('')
                                                    ->state('Deleting the image will permanently remove it from the CDN.')
                                                    ->color('gray')
                                                    ->columnSpanFull(),
                                            ])->columnSpanFull(),
                                        ])
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->columnSpanFull()
                        ->contained(false),
                ])
                ->columnSpanFull(),
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
