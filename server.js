const express = require("express");
const app = express();
const PORT = process.env.PORT || 3000;

let fetchFn = global.fetch;
if (!fetchFn) {
  try {
    // node-fetch@2
    fetchFn = require("node-fetch");
  } catch (e) {
    console.error(
      "No global fetch and node-fetch not installed. Install node-fetch or use Node 18+"
    );
    process.exit(1);
  }
}

// serve a simple HTML page (single file app)
app.get("/", (req, res) => {
  res.type("html").send(`<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>YouTube Extractor (with proxy)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body class="p-4">
<div class="container">
  <h1>YouTube URL Extractor</h1>
  <form id="form">
    <div class="form-group">
      <label>URL:</label>
      <input id="url" class="form-control" type="url" placeholder="https://example.com" required />
    </div>
    <div class="form-group">
      <label><input type="checkbox" id="titles" /> Extract titles (may be slow)</label>
    </div>
    <button class="btn btn-primary">Get URLs</button>
  </form>

  <div id="msg" class="mt-3"></div>
  <div id="results" class="mt-3"></div>
</div>

<script>
document.getElementById('form').addEventListener('submit', async e => {
  e.preventDefault();
  const url = document.getElementById('url').value.trim();
  const wantTitles = document.getElementById('titles').checked;
  const msg = document.getElementById('msg');
  const results = document.getElementById('results');
  msg.textContent = '';
  results.innerHTML = '';

  try {
    msg.textContent = 'Fetching page (via server proxy)...';
    const res = await fetch('/fetch?url=' + encodeURIComponent(url));
    if (!res.ok) throw new Error('Proxy fetch failed: ' + res.status);
    const text = await res.text();
    const matches = [...text.matchAll(/(?:watch\\/?\\?v=|youtu\\.be\\/)([\\w\\-]{11})/g)];
    if (matches.length === 0) {
      msg.textContent = 'No YouTube links found.';
      return;
    }
    const unique = [...new Set(matches.map(m=>m[1]))];
    msg.textContent = 'Found ' + unique.length + ' links.';
    const list = document.createElement('div');
    for (const id of unique) {
      const a = document.createElement('a');
      a.href = 'https://www.youtube.com/watch?v=' + id;
      a.textContent = a.href;
      a.target = '_blank';
      const wrap = document.createElement('div');
      wrap.appendChild(a);
      list.appendChild(wrap);

      if (wantTitles) {
        // fetch title via server-side proxy title endpoint
        try {
          const tRes = await fetch('/title?url=' + encodeURIComponent(a.href));
          if (tRes.ok) {
            const t = await tRes.text();
            a.textContent = t + ' (' + a.href + ')';
          }
        } catch(err) { /* ignore title errors */ }
      }
    }
    results.appendChild(list);
  } catch(err) {
    msg.textContent = 'Error: ' + err.message;
  }
});
</script>
</body>
</html>`);
});

app.get("/fetch", async (req, res) => {
  const target = req.query.url;
  if (!target) return res.status(400).send("Missing url");
  try {
    const r = await fetchFn(target, { redirect: "follow" });
    if (!r.ok)
      return res.status(502).send("Upstream fetch failed: " + r.status);
    const text = await r.text();
    res.type("text/html").send(text);
  } catch (e) {
    res.status(500).send("Fetch error: " + e.message);
  }
});

app.get("/title", async (req, res) => {
  const target = req.query.url;
  if (!target) return res.status(400).send("");
  try {
    const r = await fetchFn(target);
    if (!r.ok) return res.status(502).send("");
    const text = await r.text();
    const matches = [
      ...text.matchAll(/(?:watch\/?\?v=|youtu\.be\/)([\w-]{11})/g),
    ];
    res.type("text/plain").send(m ? m[1].trim() : target);
  } catch (e) {
    res.status(500).send("");
  }
});

app.listen(PORT, () => console.log("Listening on http://localhost:" + PORT));
