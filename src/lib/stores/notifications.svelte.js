/**
 * Space Cadet CMS — Notification / Toast Store (Svelte 5 runes)
 */

let _toasts = $state([]);
let _nextId = 0;

export const notifications = {
  get items() { return _toasts; },

  add(message, type = 'info', duration = 4000) {
    const id = ++_nextId;
    _toasts = [..._toasts, { id, message, type }];
    if (duration > 0) {
      setTimeout(() => this.remove(id), duration);
    }
    return id;
  },

  success(msg, duration = 4000) { return this.add(msg, 'success', duration); },
  error(msg, duration = 6000)   { return this.add(msg, 'error', duration); },
  warning(msg, duration = 5000) { return this.add(msg, 'warning', duration); },
  info(msg, duration = 4000)    { return this.add(msg, 'info', duration); },

  remove(id) {
    _toasts = _toasts.filter(t => t.id !== id);
  },
};
