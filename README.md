# dipbar

A minimal REST API for leaving short notes (lines of poetry, graffiti, short texts) at physical geographic locations.

Each note has a position (`lat`/`lng`) and a visibility:

| Visibility | Who can read it |
|---|---|
| `private` (default) | Only the author |
| `collection` | Members of the note's shared collection |
| `public` | Everyone, including guests |

Built on Laravel 13 with Sanctum bearer-token auth and SQLite.

## Setup

Requires PHP 8.3+ and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate      # offers to create database/database.sqlite if missing
php artisan serve        # http://localhost:8000
```

Run the tests with `php artisan test`.

## Authentication

Register or log in to get a token, then send it on every authenticated request:

```
Authorization: Bearer <token>
Accept: application/json
```

## Endpoints

All paths are prefixed with `/api`. "Token" means an `Authorization` header is required. The endpoints marked "optional" also accept guests.

### Auth

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| POST | `/register` | – | `name`, `email`, `password` (min 8) | Returns `{ user, token }` |
| POST | `/login` | – | `email`, `password` | Returns `{ user, token }` |
| POST | `/logout` | token | – | Revokes the current token |
| GET | `/user` | token | – | The current user |

### Notes

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/notes?lat=&lng=&radius=&limit=` | optional | Visible notes near a point, nearest first. `radius` is in meters (default 500, max 50 000); `limit` defaults to 50 (max 100) |
| GET | `/notes/{id}` | optional | A single note (404 if you can't see it) |
| GET | `/notes/mine` | token | Your own notes, newest first, paginated |
| POST | `/notes` | token | `body` (max 2000), `lat`, `lng`, optional `visibility`, `collection_id` |
| PATCH | `/notes/{id}` | token, author | Any of the fields above |
| DELETE | `/notes/{id}` | token, author | – |

A `collection` note needs a `collection_id` for a collection you belong to. With any other visibility, `collection_id` is cleared.

Example note:

```json
{
  "id": 1,
  "body": "Here the river forgets its name",
  "lat": 45.4642,
  "lng": 9.19,
  "visibility": "public",
  "collection_id": null,
  "author": { "id": 1, "name": "Ada" },
  "distance_m": 14,
  "created_at": "2026-10-01T21:08:07.000000Z",
  "updated_at": "2026-10-01T21:08:07.000000Z"
}
```

`distance_m` is only included in nearby search results.

### Collections

A collection is a named group of users who share `collection` notes. Its creator is the owner and is always a member.

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/collections` | token | Collections you belong to |
| POST | `/collections` | token | `name`; you become the owner |
| GET | `/collections/{id}` | token, member | Includes the member list |
| PATCH | `/collections/{id}` | token, owner | `name` |
| DELETE | `/collections/{id}` | token, owner | Its notes become `private` |
| POST | `/collections/{id}/members` | token, owner | `email` of an existing user |
| DELETE | `/collections/{id}/members/{userId}` | token, owner or that member | Remove a member, or leave the collection. The owner can't leave |

## Example

```bash
API=http://localhost:8000/api
TOKEN=$(curl -s -H 'Accept: application/json' \
  -d name=Ada -d email=ada@example.com -d password=secret123 \
  $API/register | jq -r .token)

curl -s -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -d body='Here the river forgets its name' -d lat=45.4642 -d lng=9.19 -d visibility=public \
  $API/notes

curl -s -H 'Accept: application/json' "$API/notes?lat=45.4641&lng=9.1901&radius=100"
```

## Notes on the design

- **Hidden means not found.** Notes and collections you can't access return 404 rather than 403, so their existence isn't revealed.
- **Nearby search** narrows candidates with a lat/lng bounding box in SQL, then computes exact haversine distances in PHP. That's fine at small scale; for large datasets, consider PostGIS or a geohash index.
- **Not implemented yet:** rate limiting and content moderation.

## Code map

| Path | Purpose |
|---|---|
| `routes/api.php` | All routes |
| `app/Http/Controllers/` | `AuthController`, `NoteController`, `NoteCollectionController` |
| `app/Models/Note.php` | `visibleTo` and `near` query scopes, distance calculation |
| `app/Models/NoteCollection.php` | Collection, its owner and members |
| `app/Enums/Visibility.php` | `private` / `collection` / `public` |
| `app/Http/Resources/NoteResource.php` | Note JSON shape |
| `database/migrations/2026_10_01_220000_create_notes_tables.php` | `notes`, `note_collections`, `note_collection_user` tables |
| `tests/Feature/NotesApiTest.php` | API feature tests |
