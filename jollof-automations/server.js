/**
 * Jollof Automations — Standalone Production & Development Server
 * Zero external npm dependencies. Native Node.js HTTP server.
 */
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3030;
const PUBLIC_DIR = __dirname;
const LEADS_FILE = path.join(__dirname, 'api', 'leads_store.json');

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon'
};

const server = http.createServer((req, res) => {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    return res.end();
  }

  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  let pathname = url.pathname;

  // Standalone Lead Ingestion Endpoint (POST /api/leads.php or /api/leads)
  if ((pathname === '/api/leads.php' || pathname === '/api/leads') && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const payload = JSON.parse(body || '{}');
        const ref = 'JA-' + new Date().getFullYear() + '-' + Math.random().toString(36).substring(2, 8).toUpperCase();

        let leads = [];
        if (fs.existsSync(LEADS_FILE)) {
          try { leads = JSON.parse(fs.readFileSync(LEADS_FILE, 'utf8')); } catch (e) { leads = []; }
        }

        const newLead = {
          reference: ref,
          name: payload.name || 'Anonymous',
          email: payload.email || '',
          phone: payload.phone || '',
          city: payload.city || '',
          property_type: payload.property_type || '',
          estimated_budget: payload.estimated_budget || '',
          scope: payload.scope || [],
          preferred_date: payload.preferred_date || '',
          notes: payload.notes || '',
          timestamp: new Date().toISOString()
        };

        leads.push(newLead);
        fs.writeFileSync(LEADS_FILE, JSON.stringify(leads, null, 2), 'utf8');

        res.writeHead(200, { 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({
          ok: true,
          message: `Thank you, ${newLead.name}. Your site survey request has been registered. Reference: ${ref}`,
          data: { reference: ref, estimate: newLead.estimated_budget }
        }));
      } catch (err) {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({ ok: false, message: 'Invalid JSON payload' }));
      }
    });
    return;
  }

  // Static file serving
  if (pathname === '/' || pathname === '') {
    pathname = '/index.html';
  }

  const filePath = path.join(PUBLIC_DIR, pathname);

  // Guard directory traversal
  if (!filePath.startsWith(PUBLIC_DIR)) {
    res.writeHead(403);
    return res.end('Access Denied');
  }

  fs.stat(filePath, (err, stats) => {
    if (err || !stats.isFile()) {
      res.writeHead(404, { 'Content-Type': 'text/plain' });
      return res.end('404 Not Found');
    }

    const ext = path.extname(filePath).toLowerCase();
    const contentType = MIME_TYPES[ext] || 'application/octet-stream';

    res.writeHead(200, { 'Content-Type': contentType });
    fs.createReadStream(filePath).pipe(res);
  });
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`\n======================================================`);
  console.log(`  JOLLOF AUTOMATIONS — STANDALONE SERVER READY`);
  console.log(`  Local URL:   http://localhost:${PORT}`);
  console.log(`  Directory:   ${PUBLIC_DIR}`);
  console.log(`======================================================\n`);
});
