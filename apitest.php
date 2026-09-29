<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>API Tester</title>
  <style>
    body { font-family: sans-serif; max-width: 700px; margin: 30px auto; }
    input, select, textarea, button { width: 100%; padding: 8px; margin: 4px 0; box-sizing: border-box; }
    pre { background: #111; color: #0f0; padding: 12px; overflow: auto; min-height: 120px; }
  </style>
</head>
<body>
  <h3>noblerole API tester</h3>
  <input id="key" type="password" placeholder="I-paste ang API key dito (hindi ito sine-save)">
  <select id="method">
    <option>GET</option><option>POST</option><option>PUT</option><option>DELETE</option>
  </select>
  <input id="url" value="/nobleaccounting/apinoblerole">
  <textarea id="body" rows="5" placeholder='JSON body para sa POST/PUT, hal. {"name":"Juan","email":"juan@test.com","role":"admin","password":"secret123"}'></textarea>
  <button onclick="send()">Send</button>
  <pre id="out">Lalabas dito ang response...</pre>

  <script>
    async function send() {
      const method = document.getElementById('method').value;
      const opts = { method, headers: { 'X-API-KEY': document.getElementById('key').value } };
      const body = document.getElementById('body').value.trim();
      if (body && method !== 'GET' && method !== 'DELETE') {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = body;
      }
      try {
        const res = await fetch(document.getElementById('url').value, opts);
        const text = await res.text();
        let pretty = text;
        try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
        document.getElementById('out').textContent = res.status + ' ' + res.statusText + '\n\n' + pretty;
      } catch (e) {
        document.getElementById('out').textContent = 'Error: ' + e.message;
      }
    }
  </script>
</body>
</html>