# Songrequest – Spotify Playlist API for Streamer.bot & Co

A small PHP API for adding Spotify tracks to a playlist – including OAuth login, automatic removal of played songs, and simple integration with **Streamer.bot** (Channel Points / Bits) via an included Node script.

## Features

- 🎧 **Add track** via Spotify URL or `spotify:track:` URI  
- 🔐 **OAuth login** (stores login & refresh token automatically)  
- 🧹 **Auto-clean**: Removes already played songs from the playlist (with a 20-second buffer and state tracking)  
- 🗑️ **Clear playlist** via endpoint  
- ⚙️ **.env configuration** (Client ID/Secret, Redirect URI, Playlist ID, Refresh Token)  
- 🤝 **Streamer.bot compatible**: `fetch.js` reads `%rawInput%` / `%message%` and returns a single-line response for `%output1%`  

---

## Quick Start

### Requirements

- PHP ≥ 8.1 with cURL  
- Web server (local or public)  
- Node.js (only for `fetch.js`)  
- Spotify Developer Account + App  
  - Add Redirect URI in the app (must match `.env`)  
  - Required scopes:  
    `playlist-modify-private`, `playlist-modify-public`, `user-read-playback-state`

### Installation

```bash
# Clone repository
git clone <YOUR-REPO>.git
cd Songrequest

# Create .env file
cp .env.example .env
# ... and fill in values:
# SPOTIFY_CLIENT_ID=...
# SPOTIFY_CLIENT_SECRET=...
# SPOTIFY_REDIRECT_URI=https://your-host/callback.php
# SPOTIFY_PLAYLIST_ID=spotify:playlist:... OR just the ID
# SPOTIFY_REFRESH_TOKEN= (set automatically after login)

# Optional: run locally (PHP built-in server)
php -S 127.0.0.1:8080 -t .
```

### Spotify Login (one-time)

1. **Open:** `https://<your-host>/login.php`  
2. Log in to Spotify and grant access  
3. `callback.php` automatically saves the **Refresh Token** in `.env` → `SPOTIFY_REFRESH_TOKEN=...`  

---

## Endpoints

> All responses are `application/json` with `{ ok: boolean, ... }`.  
> Errors return `{ ok:false, error: ... }` with an appropriate HTTP status.

### 1) Add track

**POST** `/add.php`  
Body (JSON):
```json
{ "url": "https://open.spotify.com/track/<ID>" }
```

**Alternatively (GET):**
```
/add.php?url=https://open.spotify.com/track/<ID>
/add.php?rawInput=text containing https://open.spotify.com/track/<ID>
```

**Example response:**
```json
{
  "ok": true,
  "message": "🎵 Added: Blu Cantrell, Sean Paul — Breathe (feat. Sean Paul) - Rap Version",
  "track_id": "<ID>",
  "title": "Breathe (feat. Sean Paul) - Rap Version",
  "artists": ["Blu Cantrell", "Sean Paul"]
}
```

Accepted formats:
- `https://open.spotify.com/track/<ID>`
- `spotify:track:<ID>`

### 2) Clear playlist

**GET** `/clear.php`  
Removes all tracks from the configured playlist.

**Response:**
```json
{ "ok": true, "message": "Playlist cleared", "removed": 12 }
```
If already empty:
```json
{ "ok": true, "message": "Playlist was already empty" }
```

### 3) Auto-clean (remove played songs)

**GET** `/autoclear.php`  

**Logic:**
- Reads active player and current track/index  
- **Mode A:** If an active player is playing your playlist → deletes all positions **before** the current track  
- **Mode B (wrap end):** If paused and the last track was played at least up to `(duration – 20s)` → deletes old entries  
- **Mode C:** No active player → nothing happens  

State file: `autoclear_state.json` (managed automatically)

**Example response:**
```json
{
  "ok": true,
  "deleted_count": 3,
  "positions": [0,1,2],
  "mode": "A",
  "api_result": { "...": "Spotify API response" }
}
```

### 4) OAuth Flow

- **GET** `/login.php` → Redirects to Spotify  
- **GET** `/callback.php` → saves `SPOTIFY_REFRESH_TOKEN` in `.env`, output: `✅ Refresh Token saved.`

---

## Configuration (.env)

```ini
SPOTIFY_CLIENT_ID=CLIENT_ID
SPOTIFY_CLIENT_SECRET=CLIENT_SECRET
SPOTIFY_REDIRECT_URI=https://your-host/callback.php
SPOTIFY_PLAYLIST_ID=PLAYLIST_ID_OR_URI
SPOTIFY_REFRESH_TOKEN=
```

**Note:** Make sure your web server **does not serve** the `.env` file (e.g., via server configuration).  
The PHP scripts will exit with an error if `.env` is missing.

---

## Streamer.bot Integration (Node Script)

File: `fetch.js`  

Acts as a "sub-action" and outputs exactly **one line** to `%output1%`.  
Reads either `DATA` (JSON), `RAW` (`%rawInput%`), or `MSG` (`%message%`).

### Examples

**1) With JSON (DATA):**
```bash
# Windows (CMD)
set URL=http://127.0.0.1:8080/add.php
set DATA={"url":"https://open.spotify.com/track/<ID>"}
node fetch.js
```

**2) With RAW (Channel Points):**
```bash
set URL=http://127.0.0.1:8080/add.php
set RAW=https://open.spotify.com/track/<ID>   # or text with link
node fetch.js
```

**3) With MSG (Bits):**
```bash
set URL=http://127.0.0.1:8080/add.php
set MSG=!song https://open.spotify.com/track/<ID>
node fetch.js
```

**Output:**
- Success: `🎵 Added: <Artist> — <Title>`
- Error: `❌ Error: <Reason>`

> The script sets `NODE_TLS_REJECT_UNAUTHORIZED=0` if not already set (for local testing only).

---

## Security & Operation

- **Protect .env:** Make sure `.env` is not publicly accessible.  
- **HTTPS** is recommended/required for OAuth redirect (depending on your Spotify app settings).  
- **Minimal scopes:** Only use the required scopes listed above.  
- **Logs/State:** `autoclear_state.json` stores the last player state.

---

## Project Structure

```
Songrequest/
├─ add.php                # Add track to playlist
├─ autoclear.php          # Automatically remove played songs
├─ autoclear_state.json   # State file for auto-clean
├─ bootstrap.php          # Helpers, .env, Spotify API wrapper
├─ callback.php           # OAuth callback, stores refresh token
├─ clear.php              # Completely clear playlist
├─ fetch.js               # Node script for Streamer.bot (sub-action)
├─ login.php              # OAuth login entry point
├─ .env                   # Local configuration (do not commit)
├─ .env.example           # Example template for .env
└─ songresult.txt         # Stores song results
```

---

## Development

- Local PHP server:
  ```bash
  php -S 127.0.0.1:8080 -t .
  ```
- Test endpoints:
  - `GET http://127.0.0.1:8080/login.php`
  - `POST http://127.0.0.1:8080/add.php` with `{ "url": "..." }`
  - `GET http://127.0.0.1:8080/clear.php`
  - `GET http://127.0.0.1:8080/autoclear.php`

---

## License

Choose an appropriate license (e.g. MIT) and update this section accordingly.

---

## Disclaimer

This project uses the Spotify Web API.  
All trademarks and names belong to their respective owners.  
Please read the [Spotify Developer Terms](https://developer.spotify.com/terms/).
