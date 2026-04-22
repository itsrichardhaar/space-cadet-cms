export function formatDate(unix, format = 'medium') {
  if (!unix) return '—';
  const d = new Date(unix * 1000);
  if (format === 'relative') return relativeTime(d);
  return d.toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    ...(format === 'long' ? { hour: '2-digit', minute: '2-digit' } : {}),
  });
}

function relativeTime(d) {
  const diff = Math.round((Date.now() - d.getTime()) / 1000);
  if (diff < 60)   return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400)return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}
