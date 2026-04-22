/**
 * Space Cadet CMS — GraphQL Client
 * Sends queries to api.php?action=graphql
 */

const ENDPOINT = '/api.php?action=graphql';

function getCsrf() {
  return window.__SC__?.csrf ?? '';
}

/**
 * Execute a GraphQL query or mutation.
 * @param {string} query   — GraphQL document string
 * @param {object} variables — optional variables map
 * @param {string} [bearerToken] — sc_ API key for mutations
 * @returns {Promise<object>} — the `data` field from the response
 */
export async function gql(query, variables = {}, bearerToken = null) {
  const headers = {
    'Content-Type': 'application/json',
    'X-CSRF-Token': getCsrf(),
  };
  if (bearerToken) {
    headers['Authorization'] = `Bearer ${bearerToken}`;
  }

  const res = await fetch(ENDPOINT, {
    method: 'POST',
    headers,
    credentials: 'same-origin',
    body: JSON.stringify({ query, variables }),
  });

  const json = await res.json().catch(() => ({
    errors: [{ message: 'Invalid server response' }],
  }));

  if (json.errors?.length) {
    const err = new Error(json.errors[0].message);
    err.errors = json.errors;
    throw err;
  }

  return json.data ?? {};
}
