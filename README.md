# Songrequest – Spotify Playlist API für Streamer.bot & Co

Kleine PHP-API zum Hinzufügen von Spotify-Tracks in eine Playlist, inkl. OAuth-Login, automatischem Aufräumen gespielter Songs und einfacher Anbindung an **Streamer.bot** (Channel Points / Bits) über ein mitgeliefertes Node-Script.

## Features

- 🎧 **Track hinzufügen** per Spotify‐URL oder `spotify:track:` URI  
- 🔐 **OAuth-Flow** (Login & Refresh Token speichern)  
- 🧹 **Auto-Clear**: Löscht bereits gespielte Playlist-Einträge (mit 20-Sekunden-Puffer & State-Tracking)  
- 🗑️ **Playlist leeren** via Endpoint  
- ⚙️ **.env**-Konfiguration (Client ID/Secret, Redirect URI, Playlist ID, Refresh Token)  
- 🤝 **Streamer.bot-ready**: `fetch.js` liest `%rawInput%` / `%message%` und gibt eine Einzeilen-Antwort für `%output1%` zurück  

---

## Schnellstart

### Voraussetzungen

- PHP ≥ 8.1 mit cURL
- Webserver (lokal oder öffentlich erreichbar)
- Node.js (nur für `fetch.js`)
- Spotify Developer Account + App  
  - Redirect URI in der App hinterlegen (muss mit `.env` übereinstimmen)
  - Benötigte Scopes:  
    `playlist-modify-private`, `playlist-modify-public`, `user-read-playback-state`

### Installation

```bash
# Repo klonen
git clone <DEIN-REPO>.git
cd Songrequest

# .env erzeugen
cp .env.example .env
# ... und Werte einsetzen:
# SPOTIFY_CLIENT_ID=...
# SPOTIFY_CLIENT_SECRET=...
# SPOTIFY_REDIRECT_URI=https://dein-host/callback.php
# SPOTIFY_PLAYLIST_ID=spotify:playlist:... ODER die reine ID
# SPOTIFY_REFRESH_TOKEN= (wird nach Login automatisch gesetzt)

# Optional lokal starten (PHP Built-in Server)
php -S 127.0.0.1:8080 -t .
```

### Spotify Login (einmalig)

1. **Aufrufen:** `https://<dein-host>/login.php`  
2. Bei Spotify einloggen & Zugriff erlauben  
3. `callback.php` speichert den **Refresh Token** automatisch in `.env` → `SPOTIFY_REFRESH_TOKEN=...`  

---

## Endpoints

> Alle Antworten sind `application/json` mit `{ ok: boolean, ... }`.  
> Fehler liefern `{ ok:false, error: ... }` und passenden HTTP-Status.

### 1) Track hinzufügen

**POST** `/add.php`  
Body (JSON):
```json
{ "url": "https://open.spotify.com/track/<ID>" }
```

**Alternativ (GET):**
```
/add.php?url=https://open.spotify.com/track/<ID>
/add.php?rawInput=irgendein Text mit https://open.spotify.com/track/<ID>
```

**Antwort (Beispiel):**
```json
{
  "ok": true,
  "message": "🎵 Hinzugefügt: Blu Cantrell, Sean Paul — Breathe (feat. Sean Paul) - Rap Version",
  "track_id": "<ID>",
  "title": "Breathe (feat. Sean Paul) - Rap Version",
  "artists": ["Blu Cantrell", "Sean Paul"]
}
```

Akzeptiert werden:
- `https://open.spotify.com/track/<ID>`
- `spotify:track:<ID>`

### 2) Playlist leeren

**GET** `/clear.php`  
Löscht alle Einträge aus der konfigurierten Playlist.

**Antwort:**
```json
{ "ok": true, "message": "Playlist geleert", "removed": 12 }
```
Wenn bereits leer:
```json
{ "ok": true, "message": "Playlist war bereits leer" }
```

### 3) Auto-Clear (gespielte Songs entfernen)

**GET** `/autoclear.php`  

Logik:
- Liest aktiven Player & aktuellen Track/Index
- **Modus A:** Wenn ein aktiver Player deine Playlist spielt → löscht alle Positionen **vor** dem aktuellen Track  
- **Modus B (Wrap-Ende):** Wenn pausiert und der letzte Track bis mind. `(duration - 20s)` gespielt wurde → löscht alte Einträge  
- **Modus C:** Kein aktiver Player → nichts löschen

State-Datei: `autoclear_state.json` (wird automatisch gepflegt)

**Antwort (Beispiel):**
```json
{
  "ok": true,
  "deleted_count": 3,
  "positions": [0,1,2],
  "mode": "A",
  "api_result": { "...": "Spotify API response" }
}
```

### 4) OAuth-Flow

- **GET** `/login.php` → Weiterleitung zu Spotify
- **GET** `/callback.php` → speichert `SPOTIFY_REFRESH_TOKEN` in `.env`, Ausgabe: `✅ Refresh Token gespeichert.`

---

## Konfiguration (.env)

```ini
SPOTIFY_CLIENT_ID=CLIENT_ID
SPOTIFY_CLIENT_SECRET=CLIENT_SECRET
SPOTIFY_REDIRECT_URI=https://dein-host/callback.php
SPOTIFY_PLAYLIST_ID=PLAYLIST_ID_ODER_URI
SPOTIFY_REFRESH_TOKEN=
```

**Hinweis:** Achte darauf, dass dein Webserver **.env** nicht ausliefert (z. B. via Server-Konfiguration). Die PHP-Scripts brechen mit Fehlermeldung ab, wenn `.env` fehlt.

---

## Streamer.bot-Integration (Node-Script)

Datei: `fetch.js`  

Verhält sich als „Sub-Action“ und gibt genau **eine Zeile** für `%output1%` aus.  
Liest **entweder** `DATA` (fertiges JSON) **oder** `RAW` (`%rawInput%`) **oder** `MSG` (`%message%`).

### Aufrufbeispiele

**1) Mit fertigem JSON (DATA):**
```bash
# Windows (CMD)
set URL=http://127.0.0.1:8080/add.php
set DATA={"url":"https://open.spotify.com/track/<ID>"}
node fetch.js
```

**2) Mit RAW (Channel Points):**
```bash
set URL=http://127.0.0.1:8080/add.php
set RAW=https://open.spotify.com/track/<ID>   # oder Text mit Link
node fetch.js
```

**3) Mit MSG (Bits):**
```bash
set URL=http://127.0.0.1:8080/add.php
set MSG=!song https://open.spotify.com/track/<ID>
node fetch.js
```

**Ausgabe:**
- Erfolg: `🎵 Hinzugefügt: <Artist> — <Title>`
- Fehler: `❌ Fehler: <Grund>`

> Das Script setzt `NODE_TLS_REJECT_UNAUTHORIZED=0`, falls nicht gesetzt (nur für lokale Tests).

---

## Sicherheit & Betrieb

- **.env schützen:** Stelle sicher, dass `.env` nicht öffentlich abrufbar ist.  
- **HTTPS** für OAuth-Redirect empfohlen/erforderlich (je nach Spotify-App-Einstellung).  
- **Scopes sparsam:** Die App benötigt nur die angegebenen Scopes.  
- **Logs/State:** `autoclear_state.json` enthält den letzten Player-State.

---

## Projektstruktur

```
Songrequest/
├─ add.php                # Track zu Playlist hinzufügen
├─ autoclear.php          # Gespielte Songs automatisch entfernen
├─ autoclear_state.json   # State-Datei für Auto-Clear
├─ bootstrap.php          # Helpers, .env, Spotify-API-Wrapper
├─ callback.php           # OAuth Callback, speichert Refresh Token
├─ clear.php              # Playlist vollständig leeren
├─ fetch.js               # Node-Script für Streamer.bot (Sub-Action)
├─ login.php              # OAuth Login-Einstieg
├─ .env                   # lokale Konfiguration (nicht kommittieren)
├─ .env.example           # Template für .env
└─ songresult.txt         # Datei zur Speicherung der Songs
```

---

## Entwicklung

- PHP Built-in Server:
  ```bash
  php -S 127.0.0.1:8080 -t .
  ```
- Testaufrufe:
  - `GET http://127.0.0.1:8080/login.php`
  - `POST http://127.0.0.1:8080/add.php` mit `{ "url": "..." }`
  - `GET http://127.0.0.1:8080/clear.php`
  - `GET http://127.0.0.1:8080/autoclear.php`

---

## License

Wähle eine passende Lizenz (z. B. MIT) und ersetze diesen Abschnitt entsprechend.

---

## Disclaimer

Dieses Projekt nutzt die Spotify Web API. Alle Marken- und Namensrechte liegen bei ihren jeweiligen Inhabern. Bitte beachte die [Spotify Developer Terms](https://developer.spotify.com/terms/).
