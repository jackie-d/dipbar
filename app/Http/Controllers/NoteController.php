<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\NoteCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NoteController extends Controller
{
    /**
     * Notes near a point, nearest first. Guests only see public notes.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['integer', 'between:1,50000'],
            'limit' => ['integer', 'between:1,100'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $radius = (int) ($data['radius'] ?? 500);

        $notes = Note::visibleTo($request->user('sanctum'))
            ->near($lat, $lng, $radius)
            ->with('user')
            ->get()
            ->each(fn (Note $note) => $note->distance = $note->distanceTo($lat, $lng))
            ->filter(fn (Note $note) => $note->distance <= $radius)
            ->sortBy('distance')
            ->take((int) ($data['limit'] ?? 50))
            ->values();

        return NoteResource::collection($notes);
    }

    /**
     * The authenticated user's own notes, newest first.
     */
    public function mine(Request $request)
    {
        return NoteResource::collection($request->user()->notes()->with('user')->latest()->paginate(50));
    }

    public function show(Request $request, Note $note)
    {
        abort_unless(Note::visibleTo($request->user('sanctum'))->whereKey($note->id)->exists(), 404);

        return new NoteResource($note->load('user'));
    }

    public function store(Request $request)
    {
        $note = $request->user()->notes()->create($this->validated($request));

        return (new NoteResource($note->load('user')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Note $note)
    {
        abort_unless($note->user_id === $request->user()->id, 404);

        $note->update($this->validated($request, $note));

        return new NoteResource($note->load('user'));
    }

    public function destroy(Request $request, Note $note)
    {
        abort_unless($note->user_id === $request->user()->id, 404);

        $note->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Note $note = null): array
    {
        $required = $note ? 'sometimes' : 'required';

        $data = $request->validate([
            'body' => [$required, 'string', 'max:2000'],
            'lat' => [$required, 'numeric', 'between:-90,90'],
            'lng' => [$required, 'numeric', 'between:-180,180'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            'collection_id' => ['nullable', 'integer', 'exists:note_collections,id'],
        ]);

        $visibility = Visibility::tryFrom($data['visibility'] ?? '') ?? $note?->visibility ?? Visibility::Private;
        $collectionId = array_key_exists('collection_id', $data) ? $data['collection_id'] : $note?->note_collection_id;

        if ($visibility === Visibility::Collection) {
            $this->ensureMember($request->user(), $collectionId);
        } else {
            $collectionId = null;
        }

        unset($data['collection_id']);

        return [...$data, 'visibility' => $visibility, 'note_collection_id' => $collectionId];
    }

    private function ensureMember(User $user, ?int $collectionId): void
    {
        $collection = $collectionId ? NoteCollection::find($collectionId) : null;

        if (! $collection?->hasMember($user)) {
            throw ValidationException::withMessages([
                'collection_id' => 'A collection you belong to is required for collection visibility.',
            ]);
        }
    }
}
