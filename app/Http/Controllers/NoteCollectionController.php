<?php

namespace App\Http\Controllers;

use App\Models\NoteCollection;
use App\Models\User;
use Illuminate\Http\Request;

class NoteCollectionController extends Controller
{
    /**
     * Collections the user owns or belongs to.
     */
    public function index(Request $request)
    {
        return $request->user()->collections()->withCount('members')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $collection = new NoteCollection($data);
        $collection->owner()->associate($request->user())->save();
        $collection->members()->attach($request->user());

        return response()->json($collection, 201);
    }

    public function show(Request $request, NoteCollection $collection)
    {
        abort_unless($collection->hasMember($request->user()), 404);

        return $collection->load('members:id,name');
    }

    public function update(Request $request, NoteCollection $collection)
    {
        $this->ensureOwner($request, $collection);

        $collection->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return $collection;
    }

    public function destroy(Request $request, NoteCollection $collection)
    {
        $this->ensureOwner($request, $collection);

        // Notes shared only with this collection fall back to private.
        $collection->notes()->update(['visibility' => 'private', 'note_collection_id' => null]);
        $collection->delete();

        return response()->noContent();
    }

    /**
     * Owner adds a member by email.
     */
    public function addMember(Request $request, NoteCollection $collection)
    {
        $this->ensureOwner($request, $collection);

        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);

        $collection->members()->syncWithoutDetaching(User::where('email', $data['email'])->value('id'));

        return $collection->load('members:id,name');
    }

    /**
     * Owner removes a member, or a member leaves. The owner can't leave their own collection.
     */
    public function removeMember(Request $request, NoteCollection $collection, User $user)
    {
        $me = $request->user();

        abort_unless($collection->owner_id === $me->id || $user->is($me), 403);
        abort_if($user->id === $collection->owner_id, 422, 'The owner cannot leave the collection.');

        $collection->members()->detach($user);

        return response()->noContent();
    }

    private function ensureOwner(Request $request, NoteCollection $collection): void
    {
        abort_unless($collection->hasMember($request->user()), 404);
        abort_unless($collection->owner_id === $request->user()->id, 403);
    }
}
