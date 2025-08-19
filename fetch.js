// fetch.js — Channel Points ODER Bits in einer Subaction
// nimmt DATA (fertiges JSON) ODER RAW (%rawInput%) ODER MSG (%message%)
// schreibt eine EINZEILIGE Antwort für %output1%
// zusätzlich wird die Ausgabe in songresult.txt gespeichert

if (process.env.NODE_TLS_REJECT_UNAUTHORIZED == null) {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0"; // nur lokal
}

const fs = require("fs");
const urlTarget = process.env.URL || "";
let   dataEnv   = process.env.DATA || "";  // {"url":"..."} – optional
const rawEnv    = process.env.RAW  || "";  // %rawInput% (Channel Points)
const msgEnv    = process.env.MSG  || "";  // %message%   (Bits)

if (dataEnv) { // falls jemand "DATA = {...}" reinkopiert hat → auf {...} kürzen
  const i = dataEnv.indexOf("{"); const j = dataEnv.lastIndexOf("}");
  if (i >= 0 && j > i) dataEnv = dataEnv.slice(i, j + 1);
}

function buildTrackUrlFromText(text) {
  if (!text) return null;
  let t = text.trim();
  try { t = decodeURIComponent(t); } catch {}
  const qPos = t.indexOf("?"); if (qPos >= 0) t = t.slice(0, qPos);
  t = t.replace(/\/intl-[^/]+\/(track\/)/i, "/$1"); // intl-xx entfernen
  const m = t.match(/(?:https?:\/\/)?open\.spotify\.com\/track\/([A-Za-z0-9]{22})|spotify:track:([A-Za-z0-9]{22})/i);
  const id = m ? (m[1] || m[2] || "").trim() : "";
  return id ? "https://open.spotify.com/track/" + id : null;
}

(async () => {
  try {
    if (!urlTarget) { console.log("❌ Fehler: URL fehlt"); return; }

    // 1) Body bestimmen
    let bodyStr = dataEnv;
    if (!bodyStr) {
      // Priorität: RAW (Channel Points) > MSG (Bits)
      const source = rawEnv || msgEnv || "";
      const trackUrl = buildTrackUrlFromText(source);
      if (!trackUrl) { console.log("❌ Fehler: Kein gültiger Spotify-Track"); return; }
      bodyStr = JSON.stringify({ url: trackUrl });
    }

    // 2) POST an add.php
    const res  = await fetch(urlTarget, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: bodyStr
    });

    const text = await res.text();

    // 3) Nur die Chatzeile zurückgeben (oder Fehler)
    try {
      const payload = JSON.parse(text);
      if (payload && payload.ok && payload.message) {
        console.log(payload.message);                 // 🎵 Hinzugefügt: Artist — Title
        // ⬇️ NEU: auch in songresult.txt speichern
        fs.writeFileSync("songresult.txt", payload.message + "\n");
      } else if (payload && (payload.error || payload.message)) {
        console.log("❌ Fehler: " + (payload.error || payload.message));
      } else {
        console.log("❌ Fehler: Leere Antwort");
      }
    } catch {
      console.log(text && text.trim() ? text.trim() : "❌ Fehler: Leere Antwort");
    }
  } catch (e) {
    console.log("❌ Fehler: " + (e.message || e));
  }
})();
