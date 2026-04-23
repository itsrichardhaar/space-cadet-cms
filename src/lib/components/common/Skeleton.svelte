<script>
  const { height = '14px', width = '100%', radius = 'var(--sc-radius)', class: cls = '' } = $props();
</script>

<div class="sk {cls}" style="height:{height};width:{width};border-radius:{radius}"></div>

<style>
  .sk {
    display: block;
    background: linear-gradient(
      90deg,
      var(--sc-surface-2) 25%,
      var(--sc-surface)   50%,
      var(--sc-surface-2) 75%
    );
    background-size: 200% 100%;
    animation: sk-shimmer 1.5s ease-in-out infinite;
  }
  @keyframes sk-shimmer {
    from { background-position: 200% 0; }
    to   { background-position: -200% 0; }
  }
</style>
