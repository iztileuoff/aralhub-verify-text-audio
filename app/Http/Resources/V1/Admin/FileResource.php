<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin File */
class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'path' => $this->path,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status,
            'rows_total' => $this->rows_total,
            'rows_imported' => $this->rows_imported,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
