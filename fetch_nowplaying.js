if (process.env.NODE_TLS_REJECT_UNAUTHORIZED == null) {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0";
}

const fs = require("fs");
const urlTarget = process.env.URL || "";

(async () => {
  try {
    if (!urlTarget) {
      const out = "❌ Fehler: URL fehlt";
      console.log(out);
      fs.writeFileSync("nowplaying.txt", out + "\n");
      return;
    }

    // leerer Body → song.php liefert aktuellen Track
    const res = await fetch(urlTarget, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: ""
    });

    const text = await res.text();

    try {
      const payload = JSON.parse(text);

      if (payload && payload.ok && payload.message) {
        console.log(payload.message);
        fs.writeFileSync("nowplaying.txt", payload.message + "\n");
        return;
      }

      // Kein aktiver Track
      if (payload && payload.error && /kein aktiver spotify-track/i.test(payload.error)) {
        const out = "🎧 Aktuell wird kein Song abgespielt";
        console.log(out);
        fs.writeFileSync("nowplaying.txt", out + "\n");
        return;
      }

      // sonstiger Fehler
      const err = payload?.error || payload?.message || "Unbekannter Fehler";
      const out = "❌ Fehler: " + err;
      console.log(out);
      fs.writeFileSync("nowplaying.txt", out + "\n");

    } catch {
      const out = text && text.trim()
        ? text.trim()
        : "❌ Fehler: Leere Antwort";
      console.log(out);
      fs.writeFileSync("nowplaying.txt", out + "\n");
    }

  } catch (e) {
    const out = "❌ Fehler: " + (e.message || e);
    console.log(out);
    fs.writeFileSync("nowplaying.txt", out + "\n");
  }
})();
