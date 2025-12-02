# Songrequest – Spotify Playlist API for Streamer.bot & Co

A small PHP API for adding Spotify tracks to a playlist – including OAuth login, automatic removal of played songs, and simple integration with **Streamer.bot** (Channel Points / Bits) via an included Node script.

## Features

- 🎧 **Add track** via Spotify URL or `spotify:track:` URI  
- 🔐 **OAuth login** (stores login & refresh token automatically — now supports MAIN & AUTOCLEAR)  
- 🧹 **Auto-clean**: Removes already played songs from the playlist (with a 20-second buffer and state tracking)  
- 🗑️ **Clear playlist** via endpoint  
- ⚙️ **.env configuration** (Client ID/Secret, Redirect URI, Playlist ID, Refresh Tokens for MAIN & AUTOCLEAR)  
- 🤝 **Streamer.bot compatible**: `fetch.js` reads `%rawInput%` / `%message%` and returns a single-line response for `%output1%`  

---

## Quick Start

### Requirements

- PHP ≥ 8.1 with cURL  
- Web server (local or public)  
- Node.js (only for `fetch.js`)  
- Spotify Developer Account + **two apps**  
  - MAIN → Für Songrequests  
  - AUTOCLEAR → Für automatisches Löschen  
  - Add Redirect URI in both apps (must match `.env`)  
  - Required scopes:  
    `playlist-modify-private`, `playlist-modify-public`, `user-read-playback-state`

### Installation

```bash
# Clone repository
git clone <YOUR-REPO>.git
cd Songrequest

# Create .env file
cp .env.example .env
# Fill in values for BOTH apps:
# SPOTIFY_CLIENT_ID_MAIN=...
# SPOTIFY_CLIENT_SECRET_MAIN=...
# SPOTIFY_CLIENT_ID_AUTOCLEAR=...
# SPOTIFY_CLIENT_SECRET_AUTOCLEAR=...
# SPOTIFY_REDIRECT_URI=https://your-host/callback.php
# SPOTIFY_PLAYLIST_ID=...
# Refresh tokens are set automatically after login
```

### Spotify Login (one-time per App)

#### MAIN Login:
```
https://<your-host>/login.php?app=main
```

#### AUTOCLEAR Login:
```
https://<your-host>/login.php?app=autoclear
```

`callback.php` stores automatically:
```
SPOTIFY_REFRESH_TOKEN_MAIN=...
SPOTIFY_REFRESH_TOKEN_AUTOCLEAR=...
```

If Spotify does not send a refresh token, remove access here:  
https://www.spotify.com/account/apps/

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

### 2) Clear playlist

**GET** `/clear.php`  
Removes all tracks from the configured playlist.

### 3) Auto-clean (remove played songs)

**GET** `/autoclear.php`  

**Logic:**
- Reads active player and current track/index  
- Mode A: Deletes everything before current track  
- Mode B: Deletes old tracks after near-end playback  
- Mode C: No active player → no action  

Uses: `autoclear_state.json`

### 4) OAuth Flow

- `GET /login.php?app=main`  
- `GET /login.php?app=autoclear`  
- `GET /callback.php` → saves corresponding refresh token  

---

## Configuration (.env)

```ini
# MAIN APP
SPOTIFY_CLIENT_ID_MAIN=
SPOTIFY_CLIENT_SECRET_MAIN=
SPOTIFY_REFRESH_TOKEN_MAIN=

# AUTOCLEAR APP
SPOTIFY_CLIENT_ID_AUTOCLEAR=
SPOTIFY_CLIENT_SECRET_AUTOCLEAR=
SPOTIFY_REFRESH_TOKEN_AUTOCLEAR=

# Shared
SPOTIFY_REDIRECT_URI=https://your-host/callback.php
SPOTIFY_PLAYLIST_ID=PLAYLIST_ID_OR_URI
```

Ensure `.env` cannot be accessed by the webserver.

---

## Streamer.bot Integration (Node Script)

File: `fetch.js`  

Acts as a "sub-action" and outputs exactly **one line** to `%output1%`.  
Reads either `DATA`, `RAW`, or `MSG`.

---

## Security & Operation

- Protect `.env`  
- HTTPS required for OAuth redirect  
- Minimal scopes only  
- `autoclear_state.json` stores state

---

## Project Structure

```
Songrequest/
├─ add.php
├─ autoclear.php
├─ autoclear_state.json
├─ bootstrap.php
├─ callback.php
├─ clear.php
├─ fetch.js
├─ login.php
├─ .env
├─ .env.example
└─ songresult.txt
```

---

## Development

```bash
php -S 127.0.0.1:8080 -t .
```

Test endpoints:
- `GET http://127.0.0.1:8080/login.php?app=main`
- `GET http://127.0.0.1:8080/login.php?app=autoclear`
- `POST http://127.0.0.1:8080/add.php`
- `GET http://127.0.0.1:8080/clear.php`
- `GET http://127.0.0.1:8080/autoclear.php`

---

## License

Choose an appropriate license (e.g. MIT) and update this section accordingly.

---

## Disclaimer

This project uses the Spotify Web API.  
All trademarks belong to their respective owners.  
