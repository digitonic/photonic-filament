@if($mediaId)
    @livewire('photonic-media-manager', [
        'mediaId' => $mediaId,
        'preset' => $preset ?? 'original',
        'previewClasses' => $previewClasses ?? 'rounded-xl max-w-full',
        'fieldName' => $fieldName ?? 'media_id'
    ])
@else
    <div class="text-sm text-gray-500 dark:text-gray-400">
        No image available
    </div>
@endif
