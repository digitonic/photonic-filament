<?php

namespace Digitonic\Mediatonic\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Actions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MediatonicImageField extends Group
{
    protected string $relationName = 'mediatonicMedia';

    /**
     * Field key used to mount the upload "dummy" state.
     * We do not dehydrate this field to DB; it only triggers the upload.
     */
    protected string $uploadFieldName = 'mediatonic_upload';

    /**
     * CDN preset path segment used for preview URL (e.g. 'originals', 'featured').
     */
    protected string $previewPreset = 'originals';

    /**
     * Whether to show the remove action.
     */
    protected bool $showRemoveAction = true;

    /**
     * Additional CSS classes applied to the preview <img> tag.
     */
    protected string $previewClasses = 'rounded-xl max-w-full h-auto';

    protected function setUp(): void
    {
        parent::setUp();

        // Build inner schema during setup so it can react to configured properties.
        $this->schema([
            // 1) Uploader - only visible when no related media exists.
            MediatonicInput::make($this->uploadFieldName)
                // Avoid saving uploader value to model
                ->dehydrated(false)
                // single file for this helper – users can still override by extending later if needed
                ->multiple(false)
                // Hide uploader if a record already has media via relation
                ->hidden(fn (?Model $record): bool => (bool) ($record?->{$this->relationName} ?? null))
                // Ensure it records to media table by default so relation can exist
                ->recordToMedia(true)
                ->columnSpan([
                    'sm' => 2,
                ]),

            // 2) Preview placeholder - only visible when relation exists
            TextEntry::make('img_preview'.'_preview_'.Str::random(2))
                ->label('Image Preview')
                ->hidden(fn (?Model $record): bool => ! (bool) ($record?->{$this->relationName} ?? null))
                ->state(function (?Model $record) {
                    $media = $record?->{$this->relationName} ?? null;
                    if (! $record || ! $media) {
                        return 'No image available';
                    }

                    $filename = $media->filename ?? null;
                    if (! $filename) {
                        return 'No image available';
                    }

                    $html = view('mediatonic-filament::components.image', [
                        'filename' => $filename,
                        'preset' => $this->previewPreset,
                        'class' => $this->previewClasses,
                        'alt' => $filename,
                    ])->render();

                    return new HtmlString($html);
                })
                ->extraAttributes(['class' => 'prose'])
                ->columnSpanFull(),

            // 3) Remove / replace action
            Actions::make([
                Action::make('removeMediatonicImage')
                    ->label(__('Remove Image'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (?Model $record): bool => ! (bool) ($record?->{$this->relationName} ?? null))
                    ->action(function (?Model $record, $livewire) {
                        if ($record && $record->{$this->relationName}) {
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
                    }),
            ])->columnSpanFull()
                ->visible($this->showRemoveAction),
        ]);
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
}
