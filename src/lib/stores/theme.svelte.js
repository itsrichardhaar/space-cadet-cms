/**
 * Space Cadet CMS — Theme Store
 *
 * theme:      'mid' | 'dark' | 'light'   — base palette
 * hue:        0–360    — accent color hue (38 = amber default)
 * brightness: 50–150   — surface lightness scale (100 = default)
 * intensity:  0–100    — accent hue bleed into surfaces (0 = neutral)
 *
 * Inline script in app.html mirrors this logic for flash-free first paint.
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

function hslToRgb(h, s, l) {
  s /= 100; l /= 100;
  const a = s * Math.min(l, 1 - l);
  const f = n => {
    const k = (n + h / 30) % 12;
    return l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1));
  };
  return [Math.round(f(0) * 255), Math.round(f(8) * 255), Math.round(f(4) * 255)];
}

/** Shortest-path hue lerp */
function lerpHue(a, b, t) {
  const d = ((b - a + 540) % 360) - 180;
  return ((a + d * t) % 360 + 360) % 360;
}

function clamp(v, mn, mx) { return Math.max(mn, Math.min(mx, v)); }

// ── Base surface colors [h, s, l] per theme ───────────────────────────────────
// These are the neutral greys used when intensity = 0 and brightness = 100.
// At non-default values, they are HSL-tinted toward the accent hue.

const SURFACES = {
  mid: {
    bg:       [30, 3, 53],
    surface:  [30, 4, 60],
    surface2: [30, 4, 68],
    inset:    [30, 3, 44],
  },
  dark: {
    bg:       [30, 8, 15],
    surface:  [30, 7, 19],
    surface2: [30, 7, 22],
    inset:    [30, 9, 12],
  },
  light: {
    bg:       [38, 8, 74],
    surface:  [38, 8, 80],
    surface2: [38, 8, 85],
    inset:    [38, 8, 69],
  },
};

/** Accent saturation/lightness per theme, plus hover variant */
const ACCENT_SL = {
  mid:   { s: 88, l: 54, hs: 90, hl: 48 },
  dark:  { s: 88, l: 54, hs: 90, hl: 48 },
  light: { s: 72, l: 44, hs: 74, hl: 38 },
};

const OVERRIDE_PROPS = [
  '--sc-bg', '--sc-surface', '--sc-surface-2', '--sc-inset',
  '--sc-accent', '--sc-accent-rgb', '--sc-accent-hover', '--sc-accent-soft',
];

// ── Core computation ──────────────────────────────────────────────────────────

function computeVars(theme, hue, brightness, intensity) {
  const S = SURFACES[theme] ?? SURFACES.mid;
  const A = ACCENT_SL[theme]  ?? ACCENT_SL.mid;
  const t  = intensity / 100;
  const br = brightness / 100;

  const tint = ([h0, s0, l0]) => {
    const h = lerpHue(h0, hue, t);
    const s = s0 + t * 26;
    const l = clamp(l0 * br, 0, 98);
    return `hsl(${h.toFixed(1)},${s.toFixed(1)}%,${l.toFixed(1)}%)`;
  };

  const [ar, ag, ab] = hslToRgb(hue, A.s,  A.l);
  const [hr, hg, hb] = hslToRgb(hue, A.hs, A.hl);

  return {
    '--sc-bg':           tint(S.bg),
    '--sc-surface':      tint(S.surface),
    '--sc-surface-2':    tint(S.surface2),
    '--sc-inset':        tint(S.inset),
    '--sc-accent':       `rgb(${ar},${ag},${ab})`,
    '--sc-accent-rgb':   `${ar}, ${ag}, ${ab}`,
    '--sc-accent-hover': `rgb(${hr},${hg},${hb})`,
    '--sc-accent-soft':  `rgba(${ar},${ag},${ab},0.16)`,
  };
}

function applyAll(theme, hue, brightness, intensity) {
  if (typeof document === 'undefined') return;
  const el = document.documentElement;

  // Theme base via data-theme attribute (handled by CSS cascade)
  if (theme === 'mid') el.removeAttribute('data-theme');
  else                 el.setAttribute('data-theme', theme);

  // At defaults, remove inline overrides so CSS file values take effect exactly
  if (hue === 38 && brightness === 100 && intensity === 0) {
    OVERRIDE_PROPS.forEach(k => el.style.removeProperty(k));
    return;
  }

  // Otherwise: set computed overrides as inline style (highest specificity)
  const vars = computeVars(theme, hue, brightness, intensity);
  for (const [k, v] of Object.entries(vars)) el.style.setProperty(k, v);
}

// ── State ─────────────────────────────────────────────────────────────────────

let current    = $state('mid');
let hue        = $state(38);
let brightness = $state(100);
let intensity  = $state(0);

function save() {
  if (typeof localStorage === 'undefined') return;
  localStorage.setItem('sc-theme',      current);
  localStorage.setItem('sc-hue',        String(hue));
  localStorage.setItem('sc-brightness', String(brightness));
  localStorage.setItem('sc-intensity',  String(intensity));
}

// ── Public store ──────────────────────────────────────────────────────────────

export const themeStore = {
  get current()    { return current;    },
  get hue()        { return hue;        },
  get brightness() { return brightness; },
  get intensity()  { return intensity;  },

  init() {
    if (typeof localStorage === 'undefined') return;
    const t  = localStorage.getItem('sc-theme');
    const h  = parseFloat(localStorage.getItem('sc-hue')        ?? '38');
    const br = parseFloat(localStorage.getItem('sc-brightness')  ?? '100');
    const it = parseFloat(localStorage.getItem('sc-intensity')   ?? '0');
    current    = ['mid', 'dark', 'light'].includes(t) ? t : 'mid';
    hue        = isNaN(h)  ? 38  : clamp(h,  0,   360);
    brightness = isNaN(br) ? 100 : clamp(br, 50,  150);
    intensity  = isNaN(it) ? 0   : clamp(it, 0,   100);
    applyAll(current, hue, brightness, intensity);
  },

  set(t)    { current    = t;                 save(); applyAll(current, hue, brightness, intensity); },
  setHue(v) { hue        = clamp(v, 0, 360);  save(); applyAll(current, hue, brightness, intensity); },
  setBri(v) { brightness = clamp(v, 50, 150); save(); applyAll(current, hue, brightness, intensity); },
  setInt(v) { intensity  = clamp(v, 0, 100);  save(); applyAll(current, hue, brightness, intensity); },

  reset() {
    hue = 38; brightness = 100; intensity = 0;
    save(); applyAll(current, hue, brightness, intensity);
  },
};
