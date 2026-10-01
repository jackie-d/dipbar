# dipbar

A minimal REST API, with a light map frontend, for leaving short notes (lines of poetry, graffiti, short texts) at physical geographic locations.

**Live:** https://dipbar-latest.onrender.com (free hosting: the first visit after a while can take about 30 seconds, and notes are reset on each deploy)

Each note has a position (`lat`/`lng`) and a visibility:

| Visibility | Who can read it |
|---|---|
| `private` (default) | Only the author |
| `collection` | Members of the note's shared collection |
| `public` | Everyone, including guests |

Built on Laravel 13 with Sanctum auth and SQLite. The frontend uses Inertia, Vue 3 and a Leaflet/OpenStreetMap map.

## Setup

Requires PHP 8.3+, Composer and Node.js.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate      # offers to create database/database.sqlite if missing
npm run build            # or `npm run dev` for hot reload while editing the frontend
php artisan serve        # http://localhost:8000
```

Run the tests with `php artisan test`.

**GitHub Codespaces:** set `APP_URL` to the forwarded address (`https://<codespace>-8000.app.github.dev`) and add `TRUSTED_PROXIES=*` to `.env`. Otherwise asset URLs and redirects point at `127.0.0.1` and the page loads blank.

## Frontend

| Page | Path | Notes |
|---|---|---|
| Map | `/` | Notes in the visible area as colored pins (public, collection, private). Click the map to leave a note; click a pin to read it or delete your own |
| Collections | `/collections` | Create collections, add members by email, remove members or leave. Login required |
| Log in, Sign up | `/login`, `/register` | |

The frontend logs in with a regular cookie session, then calls the same `/api` endpoints. Sanctum accepts that session for same-host requests, so there are no tokens to manage in the browser.

## API authentication

API clients register or log in to get a token, then send it on every authenticated request:

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

## Docker

The `Dockerfile` builds one production image: PHP 8.4 with Apache, the built frontend, and SQLite stored on a `/data` volume. On start, the container runs migrations, caches config and routes, and serves on port 8080 as the unprivileged `www-data` user.

```bash
docker build -t dipbar .
docker run -d -p 8080:8080 \
  -e APP_KEY="$(php artisan key:generate --show)" \
  -e APP_URL=https://your-domain.example \
  -v dipbar-data:/data \
  dipbar
```

| Variable | Default | Notes |
|---|---|---|
| `APP_KEY` | – | Required. Keep it stable across deploys, or sessions are lost |
| `APP_URL` | `http://localhost` | Public URL of the app |
| `PORT` | `8080` | Port Apache listens on |
| `TRUSTED_PROXIES` | – | Set to `*` behind a load balancer or proxy that terminates HTTPS |
| `DB_DATABASE` | `/data/database.sqlite` | Keep `/data` on a persistent volume |

### GitHub Actions

`.github/workflows/docker.yml` runs the tests on every push and pull request. On pushes to `main` and `v*` tags, it then builds the image and publishes it to the GitHub Container Registry:

- `ghcr.io/plasm4e/dipbar:latest`: the latest `main`
- `ghcr.io/plasm4e/dipbar:sha-<commit>`: every build
- `ghcr.io/plasm4e/dipbar:1.2.3`: from a `v1.2.3` tag

GitHub stores the image but doesn't run it. The image is public, so any Docker host can pull it with `docker pull ghcr.io/plasm4e/dipbar:latest`.

### Render (free)

`render.yaml` runs the image on Render's free plan, with HTTPS. It's live at https://dipbar-latest.onrender.com. On this plan the app sleeps after about 15 minutes idle (the first visit after that takes 30 seconds or more). The SQLite database isn't persistent: it starts empty after every deploy or restart.

One-time setup:

1. On [render.com](https://render.com), choose **New → Blueprint**, connect this GitHub repo, and apply `render.yaml`.
2. When prompted, set `APP_KEY` to the output of `php artisan key:generate --show`, and `APP_URL` to the service's address (here `https://dipbar-latest.onrender.com`).
3. To redeploy automatically on every push to `main`, copy the service's **Deploy Hook** URL (Settings → Deploy Hook) into a GitHub Actions secret named `RENDER_DEPLOY_HOOK`. Without it, the workflow skips the deploy step, and you redeploy from the Render dashboard.



- **Hidden means not found.** Notes and collections you can't access return 404 rather than 403, so their existence isn't revealed.
- **Nearby search** narrows candidates with a lat/lng bounding box in SQL, then computes exact haversine distances in PHP. That's fine at small scale; for large datasets, consider PostGIS or a geohash index.
- **Not implemented yet:** rate limiting and content moderation.

## Code map

| Path | Purpose |
|---|---|
| `routes/api.php` | API routes |
| `routes/web.php` | Frontend pages and session login/logout |
| `app/Http/Controllers/` | `AuthController` (API tokens), `SessionController` (frontend login), `NoteController`, `NoteCollectionController` |
| `app/Models/Note.php` | `visibleTo` and `near` query scopes, distance calculation |
| `app/Models/NoteCollection.php` | Collection, its owner and members |
| `app/Enums/Visibility.php` | `private` / `collection` / `public` |
| `app/Http/Resources/NoteResource.php` | Note JSON shape |
| `database/migrations/2026_10_01_220000_create_notes_tables.php` | `notes`, `note_collections`, `note_collection_user` tables |
| `resources/js/pages/` | Vue pages: `Map`, `Collections`, `Login`, `Register` |
| `resources/js/api.js` | `fetch` wrapper for calling the API with the session cookie |
| `Dockerfile`, `docker/entrypoint.sh` | Production image and its startup script |
| `.github/workflows/docker.yml` | CI: tests, build and publish the image, redeploy on Render |
| `render.yaml` | Render Blueprint for free hosting |
| `tests/Feature/` | `NotesApiTest` (API), `FrontendTest` (pages and session auth) |
