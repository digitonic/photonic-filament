<div class="text-left mt-2 mb-2">
    @php
        $record = $getRecord();
        $relation = $getName();

        // Check the relationship exists
        $relatedModel = $record->{$relation};

        if(is_null($relatedModel)) {
            return;
        }

        $cdnUrl = photonic_asset($relatedModel->filename, $relatedModel->asset_uuid);
    @endphp

    <img src="{{ $cdnUrl }}" alt="{{ $relatedModel->alt ?? 'Image' }}" class="max-w-full h-auto rounded">
</div>