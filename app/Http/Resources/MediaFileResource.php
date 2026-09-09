<?php

namespace App\Http\Resources;

use App\Services\Media\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'url' => $disk->url($this->path),
            'thumb_url' => $this->isImage() ? $disk->url(ImageOptimizer::thumbnailPath($this->path)) : null,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'created_at' => $this->created_at,
        ];
    }
}
