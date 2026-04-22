import api from '$lib/api.js';

let _list  = $state([]);
let _loaded = $state(false);
let _loading = false;

export const collectionsStore = {
  get list()   { return _list; },
  get loaded() { return _loaded; },

  findBySlug(slug) {
    return _list.find(c => c.slug === slug) ?? null;
  },

  async load(force = false) {
    if ((_loaded && !force) || _loading) return;
    _loading = true;
    try {
      _list   = (await api.get('collections')).data ?? [];
      _loaded = true;
    } finally {
      _loading = false;
    }
  },

  upsert(c) {
    const i = _list.findIndex(x => x.id === c.id);
    if (i >= 0) _list[i] = c;
    else _list = [..._list, c];
  },

  remove(id) {
    _list = _list.filter(c => c.id !== id);
  },
};
