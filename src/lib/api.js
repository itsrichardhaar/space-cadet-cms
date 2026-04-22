/**
 * Space Cadet CMS — API Client
 * Thin fetch wrapper targeting api.php?action=<action>
 */

const BASE = '/api.php';

function getCsrf() {
  return window.__SC__?.csrf ?? '';
}

async function request(action, method = 'GET', body = null, opts = {}) {
  const url = `${BASE}?action=${action}${method !== 'GET' ? `&method=${method}` : ''}`;

  const headers = {
    'Content-Type': 'application/json',
    'X-CSRF-Token': getCsrf(),
  };

  const init = {
    method: method === 'GET' ? 'GET' : 'POST', // PHP built-in server normalises
    headers,
    credentials: 'same-origin',
  };

  if (body !== null && method !== 'GET') {
    init.body = JSON.stringify(body);
  }

  const res = await fetch(url + (method !== 'GET' ? '' : buildQuery(opts.params)), init);
  const json = await res.json().catch(() => ({ ok: false, error: { message: 'Invalid server response' } }));

  if (!res.ok || json.ok === false) {
    const err = new Error(json.error?.message ?? `HTTP ${res.status}`);
    err.status = res.status;
    err.errors = json.errors ?? null;
    throw err;
  }

  return json;
}

function buildQuery(params) {
  if (!params) return '';
  const q = Object.entries(params)
    .filter(([, v]) => v !== null && v !== undefined && v !== '')
    .map(([k, v]) => `&${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join('');
  return q;
}

// ── Convenience methods ────────────────────────────────────────────────────

export const api = {
  get:    (action, params = {}) => request(action, 'GET', null, { params }),
  post:   (action, body = {})   => request(action, 'POST', body),
  put:    (action, body = {})   => request(action, 'PUT', body),
  delete: (action)              => request(action, 'DELETE'),

  // File upload (multipart — bypass JSON body)
  upload: async (file, extraData = {}) => {
    const fd = new FormData();
    fd.append('file', file);
    for (const [k, v] of Object.entries(extraData)) fd.append(k, v);

    const res = await fetch(`${BASE}?action=media&method=POST`, {
      method: 'POST',
      headers: { 'X-CSRF-Token': getCsrf() },
      credentials: 'same-origin',
      body: fd,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.error?.message ?? 'Upload failed');
    return json;
  },
};

export default api;
