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
  if (!text) return { error: "empty" };

  let t = text.trim();
  try { t = decodeURIComponent(t); } catch {}

  const qPos = t.indexOf("?");
  if (qPos >= 0) t = t.slice(0, qPos);

  // ❗ Album-Link explizit erkennen
  if (/open\.spotify\.com\/(intl-[^/]+\/)?album\//i.test(t)) {
    return { error: "album" };
  }

  // Track-Link normalisieren
  t = t.replace(/\/intl-[^/]+\/(track\/)/i, "/$1");

  const m = t.match(
    /(?:https?:\/\/)?open\.spotify\.com\/track\/([A-Za-z0-9]{22})|spotify:track:([A-Za-z0-9]{22})/i
  );

  const id = m ? (m[1] || m[2] || "").trim() : "";
  if (!id) return { error: "invalid" };

  return { url: "https://open.spotify.com/track/" + id };
}




(async () => {
  try {
    if (!urlTarget) { console.log("❌ Fehler: URL fehlt"); return; }

    // 1) Body bestimmen
    let bodyStr = dataEnv;
    if (!bodyStr) {
  let source = rawEnv || msgEnv || "";
  // Streamer.bot Platzhalter ignorieren
if (source.startsWith("%") && source.endsWith("%")) {
  source = "";
}
  if (source.trim() !== "") {
    const result = buildTrackUrlFromText(source);

if (result.error) {
  let msg = "❌ Kein gültiger Spotify-Track-Link";

  if (result.error === "album") {
    msg = "❌ Das ist ein Album-Link – bitte poste einen einzelnen Spotify-Track";
  }

  console.log(msg);
  fs.writeFileSync("songresult.txt", msg + "\n");
  return;
}

bodyStr = JSON.stringify({ url: result.url });

  } else {
    // 👈 KEIN Link → leerer Body → fav.php nimmt aktuellen Song
    bodyStr = "";
  }
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
        fs.writeFileSync("songresult.txt", payload.message + "\n");
      } else if (payload && (payload.error || payload.message)) {
        let errMsg = "";

        if (typeof payload.error === "string") {
          errMsg = payload.error;
        } else if (typeof payload.error === "object") {
          errMsg = JSON.stringify(payload.error);
        } else if (payload.message) {
          errMsg = payload.message;
        } else {
          errMsg = "Unbekannter Fehler";
        }

        const out = "❌ Fehler beim Hinzufügen: " + errMsg;
        console.log(out);
        fs.writeFileSync("songresult.txt", out + "\n");
      } else {
        const out = "❌ Fehler: Leere Antwort";
        console.log(out);
        fs.writeFileSync("songresult.txt", out + "\n");
      }
    } catch {
      const out = text && text.trim() ? text.trim() : "❌ Fehler: Leere Antwort";
      console.log(out);
      fs.writeFileSync("songresult.txt", out + "\n");
    }
  } catch (e) {
    const out = "❌ Fehler: " + (e.message || e);
    console.log(out);
    fs.writeFileSync("songresult.txt", out + "\n");
  }
})();
