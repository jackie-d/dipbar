<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotesApiTest extends TestCase
{
    use RefreshDatabase;

    // Piazza del Colosseo, Rome
    private const LAT = 41.8902;

    private const LNG = 12.4922;

    private function note(User $user, array $attrs = []): Note
    {
        return $user->notes()->create([
            'body' => 'Some words on a wall',
            'lat' => self::LAT,
            'lng' => self::LNG,
            ...$attrs,
        ]);
    }

    private function collectionWith(User $owner, User ...$members): NoteCollection
    {
        $collection = new NoteCollection(['name' => 'Friends']);
        $collection->owner()->associate($owner)->save();
        $collection->members()->attach([$owner->id, ...array_map(fn ($u) => $u->id, $members)]);

        return $collection;
    }

    private function nearby(): array
    {
        return $this->getJson('/api/notes?lat='.self::LAT.'&lng='.self::LNG)
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_register_login_and_logout(): void
    {
        $this->postJson('/api/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret123'])
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id'], 'token']);

        $token = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('email', 'ada@example.com');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_creating_a_note_defaults_to_private(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/notes', ['body' => 'Roses are red', 'lat' => self::LAT, 'lng' => self::LNG])
            ->assertCreated()
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.collection_id', null);
    }

    public function test_guests_only_see_public_notes(): void
    {
        $author = User::factory()->create();
        $public = $this->note($author, ['visibility' => 'public']);
        $private = $this->note($author);

        $this->assertSame([$public->id], $this->nearby());
        $this->getJson("/api/notes/{$private->id}")->assertNotFound();
        $this->getJson("/api/notes/{$public->id}")->assertOk()->assertJsonPath('data.author.name', $author->name);
    }

    public function test_collection_notes_are_visible_to_members_only(): void
    {
        [$author, $member, $stranger] = User::factory()->count(3)->create();
        $collection = $this->collectionWith($author, $member);
        $shared = $this->note($author, ['visibility' => 'collection', 'note_collection_id' => $collection->id]);
        $private = $this->note($author);

        Sanctum::actingAs($author);
        $this->assertEqualsCanonicalizing([$shared->id, $private->id], $this->nearby());

        Sanctum::actingAs($member);
        $this->assertSame([$shared->id], $this->nearby());

        Sanctum::actingAs($stranger);
        $this->assertSame([], $this->nearby());
        $this->getJson("/api/notes/{$shared->id}")->assertNotFound();
    }

    public function test_collection_visibility_requires_membership(): void
    {
        [$owner, $outsider] = User::factory()->count(2)->create();
        $collection = $this->collectionWith($owner);

        Sanctum::actingAs($outsider);
        $this->postJson('/api/notes', [
            'body' => 'Sneaky', 'lat' => self::LAT, 'lng' => self::LNG,
            'visibility' => 'collection', 'collection_id' => $collection->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('collection_id');
    }

    public function test_nearby_filters_by_radius_and_sorts_by_distance(): void
    {
        $author = User::factory()->create();
        $far = $this->note($author, ['visibility' => 'public', 'lat' => self::LAT + 0.003]); // ~330 m
        $near = $this->note($author, ['visibility' => 'public', 'lat' => self::LAT + 0.0005]); // ~55 m
        $this->note($author, ['visibility' => 'public', 'lat' => self::LAT + 0.02]); // ~2.2 km

        $response = $this->getJson('/api/notes?lat='.self::LAT.'&lng='.self::LNG.'&radius=1000')->assertOk();

        $this->assertSame([$near->id, $far->id], $response->json('data.*.id'));
        $this->assertEqualsWithDelta(56, $response->json('data.0.distance_m'), 2);
    }

    public function test_only_the_author_can_update_or_delete(): void
    {
        [$author, $other] = User::factory()->count(2)->create();
        $note = $this->note($author, ['visibility' => 'public']);

        Sanctum::actingAs($other);
        $this->patchJson("/api/notes/{$note->id}", ['body' => 'Defaced'])->assertNotFound();
        $this->deleteJson("/api/notes/{$note->id}")->assertNotFound();

        Sanctum::actingAs($author);
        $this->patchJson("/api/notes/{$note->id}", ['body' => 'Revised'])->assertOk()->assertJsonPath('data.body', 'Revised');
        $this->deleteJson("/api/notes/{$note->id}")->assertNoContent();
    }

    public function test_owner_manages_collection_members(): void
    {
        [$owner, $friend] = User::factory()->count(2)->create();

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/collections', ['name' => 'Night walks'])->assertCreated()->json('id');
        $this->postJson("/api/collections/{$id}/members", ['email' => $friend->email])
            ->assertOk()
            ->assertJsonCount(2, 'members');

        Sanctum::actingAs($friend);
        $this->getJson('/api/collections')->assertOk()->assertJsonPath('0.name', 'Night walks');
        $this->postJson("/api/collections/{$id}/members", ['email' => $owner->email])->assertForbidden();
        $this->deleteJson("/api/collections/{$id}/members/{$friend->id}")->assertNoContent();
        $this->getJson("/api/collections/{$id}")->assertNotFound();
    }

    public function test_deleting_a_collection_makes_its_notes_private(): void
    {
        [$owner, $member] = User::factory()->count(2)->create();
        $collection = $this->collectionWith($owner, $member);
        $note = $this->note($member, ['visibility' => 'collection', 'note_collection_id' => $collection->id]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/collections/{$collection->id}")->assertNoContent();

        $this->assertSame('private', $note->fresh()->visibility->value);
        $this->assertNull($note->fresh()->note_collection_id);
    }
}
