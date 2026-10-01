<?php

namespace App\Models;

use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body', 'lat', 'lng', 'visibility', 'note_collection_id'])]
class Note extends Model
{
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'visibility' => Visibility::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(NoteCollection::class, 'note_collection_id');
    }

    /**
     * Notes the given user (or a guest) is allowed to read.
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', Visibility::Public);

            if ($user) {
                $q->orWhere('user_id', $user->id)
                    ->orWhere(fn (Builder $q) => $q
                        ->where('visibility', Visibility::Collection)
                        ->whereIn('note_collection_id', $user->collections()->select('note_collections.id')));
            }
        });
    }

    /**
     * Notes inside a lat/lng bounding box around a point (radius in meters).
     */
    #[Scope]
    protected function near(Builder $query, float $lat, float $lng, int $radius): void
    {
        $dLat = $radius / 111_320;
        $dLng = $radius / (111_320 * max(cos(deg2rad($lat)), 0.01));

        $query->whereBetween('lat', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('lng', [$lng - $dLng, $lng + $dLng]);
    }

    /**
     * Great-circle distance in meters (haversine).
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $dLat = deg2rad($this->lat - $lat);
        $dLng = deg2rad($this->lng - $lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($this->lat)) * sin($dLng / 2) ** 2;

        return 6_371_000 * 2 * asin(min(1, sqrt($a)));
    }
}
