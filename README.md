# Songrequest – Spotify Playlist API for Streamer.bot & Co

A small PHP API for adding Spotify tracks to a playlist – including OAuth login, automatic removal of played songs, and simple integration with **Streamer.bot** (Channel Points / Bits) via an included Node script.

## Features

- 🎧 **Add track** via Spotify URL or `spotify:track:` URI  
- ⭐ **Favorite current track or link**: Save the currently playing song *or* a provided Spotify track to a separate favorites playlist (duplicates prevented)  
- 🔐 **OAuth login** (stores login & refresh token automatically — now supports MAIN & AUTOCLEAR)  
- 🧹 **Auto-clean**: Removes already played songs from the playlist (with a 20-second buffer and state tracking)  
- 🗑️ **Clear playlist** via endpoint  
- ⚙️ **.env configuration** (Client ID/Secret, Redirect URI, Playlist ID, Favorites Playlist ID, Refresh Tokens for MAIN & AUTOCLEAR)  
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
git clone <YOUR-REPO>.git
cd Songrequest
cp .env.example .env
```

### Spotify Login (one-time per App)

https://<your-host>/login.php?app=main  
https://<your-host>/login.php?app=autoclear

---

## Endpoints

### 1) Add track

POST `/add.php`

### 1b) Favorite track

POST `/fav.php`

- Without input → saves currently playing track  
- With Spotify link → saves provided track  
- Duplicates are prevented

---

## Configuration (.env)

```ini
SPOTIFY_CLIENT_ID_MAIN=
SPOTIFY_CLIENT_SECRET_MAIN=
SPOTIFY_REFRESH_TOKEN_MAIN=

SPOTIFY_CLIENT_ID_AUTOCLEAR=
SPOTIFY_CLIENT_SECRET_AUTOCLEAR=
SPOTIFY_REFRESH_TOKEN_AUTOCLEAR=

SPOTIFY_REDIRECT_URI=https://your-host/callback.php
SPOTIFY_PLAYLIST_ID=PLAYLIST_ID
SPOTIFY_FAV_PLAYLIST_ID=FAVORITES_PLAYLIST_ID
```

---

## Streamer.bot Example

Command:
```
!favsong
!favsong https://open.spotify.com/track/<ID>
```

Environment:
```
URL=https://your-host/fav.php
RAW=%rawInput%
MSG=%message%
NODE_TLS_REJECT_UNAUTHORIZED=0
```

---

## Project Structure

Songrequest/
├─ add.php
├─ fav.php
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
