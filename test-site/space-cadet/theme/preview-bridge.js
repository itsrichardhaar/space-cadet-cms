/**
 * Space Cadet CMS — Preview Bridge
 *
 * Injected inline into every preview-mode page render.
 * Handles postMessage DOM injection from the builder iframe parent
 * and sends click/hover events back to the parent.
 *
 * Protocol (parent → iframe):
 *   { type: 'field:update',     blockIndex, field, value }
 *   { type: 'block:highlight',  blockIndex }
 *   { type: 'block:unhighlight' }
 *
 * Protocol (iframe → parent):
 *   { type: 'block:select', blockIndex }
 *   { type: 'block:hover',  blockIndex }
 *   { type: 'block:unhover' }
 */

(function () {
  'use strict';

  if (!window.__SC_PREVIEW__) return;

  // ── Inject outline styles ──────────────────────────────────────────────────
  var style = document.createElement('style');
  style.textContent = [
    '[data-block-index] { cursor: pointer; transition: outline 0.1s; }',
    '[data-block-index]:hover { outline: 2px solid rgba(79,70,229,0.4); outline-offset: 2px; }',
    '[data-block-index].sc-block--highlighted { outline: 2px solid #4f46e5; outline-offset: 2px; }',
  ].join('\n');
  document.head.appendChild(style);

  // ── Helpers ────────────────────────────────────────────────────────────────

  function blockEl(index) {
    return document.querySelector('[data-block-index="' + index + '"]');
  }

  function postToParent(msg) {
    window.parent.postMessage(msg, '*');
  }

  // ── Wire up block click + hover ────────────────────────────────────────────

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-block-index]');
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
    var idx = parseInt(el.getAttribute('data-block-index'), 10);
    postToParent({ type: 'block:select', blockIndex: idx });
  }, true);

  document.addEventListener('mouseover', function (e) {
    var el = e.target.closest('[data-block-index]');
    if (!el) return;
    var idx = parseInt(el.getAttribute('data-block-index'), 10);
    postToParent({ type: 'block:hover', blockIndex: idx });
  });

  document.addEventListener('mouseout', function (e) {
    var el = e.target.closest('[data-block-index]');
    if (!el) return;
    postToParent({ type: 'block:unhover' });
  });

  // ── Listen for messages from parent ───────────────────────────────────────

  window.addEventListener('message', function (e) {
    var msg = e.data;
    if (!msg || typeof msg !== 'object') return;

    switch (msg.type) {

      case 'field:update': {
        var el = blockEl(msg.blockIndex);
        if (!el) return;
        var target = el.querySelector('[data-field="' + msg.field + '"]');
        if (!target) return;
        // Update content based on element type
        var tag = target.tagName.toLowerCase();
        if (tag === 'img') {
          target.src = msg.value;
        } else if (tag === 'a') {
          target.textContent = msg.value;
        } else if (tag === 'input') {
          target.value = msg.value;
        } else {
          target.textContent = msg.value;
        }
        break;
      }

      case 'block:highlight': {
        // Remove highlight from all blocks first
        document.querySelectorAll('[data-block-index].sc-block--highlighted').forEach(function (el) {
          el.classList.remove('sc-block--highlighted');
        });
        var el = blockEl(msg.blockIndex);
        if (el) el.classList.add('sc-block--highlighted');
        break;
      }

      case 'block:unhighlight': {
        document.querySelectorAll('[data-block-index].sc-block--highlighted').forEach(function (el) {
          el.classList.remove('sc-block--highlighted');
        });
        break;
      }
    }
  });

}());
