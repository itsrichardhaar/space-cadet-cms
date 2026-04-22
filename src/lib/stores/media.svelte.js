/**
 * Space Cadet CMS — Media Store (Svelte 5 runes)
 */

import { api } from '../api.js';

let _items   = $state([]);
let _loaded  = false;
let _loading = false;

export const mediaStore = {
  get items()  { return _items; },
  get loaded() { return _loaded; },

  async load(force = false) {
    if ((_loaded && !force) || _loading) return;
    _loading = true;
    try {
      const res = await api.get('media', { per_page: 200 });
      _items  = res.data ?? [];
      _loaded = true;
    } finally {
      _loading = false;
    }
  },

  upsert(item) {
    const idx = _items.findIndex(m => m.id === item.id);
    _items = idx >= 0
      ? _items.map(m => m.id === item.id ? item : m)
      : [item, ..._items];
  },

  remove(id) {
    _items = _items.filter(m => m.id !== id);
  },
};
