<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'visibility' => $this->visibility,
            'collection_id' => $this->note_collection_id,
            'author' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'distance_m' => $this->when(isset($this->distance), fn () => (int) round($this->distance)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
