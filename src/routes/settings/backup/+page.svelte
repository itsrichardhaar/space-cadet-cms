<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let downloading = $state(false);
  let uploading   = $state(false);
  let confirming  = $state(false);
  let pendingInfo = $state(null);   // set after upload, before confirm
  let fileInput;

  async function download() {
    downloading = true;
    try {
      const res = await fetch('/api.php?action=backup/download', {
        headers: { 'X-CSRF-Token': window.__SC__?.csrf ?? '' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('Download failed: ' + res.status);
      const blob = await res.blob();
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href     = url;
      const cd   = res.headers.get('content-disposition') ?? '';
      const m    = cd.match(/filename="([^"]+)"/);
      a.download = m ? m[1] : 'space-cadet-backup.sqlite';
      a.click();
      URL.revokeObjectURL(url);
      notifications.success('Backup downloaded');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      downloading = false;
    }
  }

  async function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    uploading = true;
    try {
      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch('/api.php?action=backup/restore&method=POST', {
        method: 'POST',
        headers: { 'X-CSRF-Token': window.__SC__?.csrf ?? '' },
        credentials: 'same-origin',
        body: fd,
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.error?.message ?? 'Upload failed');
      pendingInfo = json.data;
      notifications.success('Backup staged — review and confirm below');
    } catch (err) {
      notifications.error(err.message);
    } finally {
      uploading = false;
      if (fileInput) fileInput.value = '';
    }
  }

  async function confirmRestore() {
    confirming = false;
    try {
      await api.post('backup/confirm');
      notifications.success('Database restored. Please log in again.');
      setTimeout(() => { window.location.href = '/admin/login'; }, 1500);
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<AdminShell title="Backup & Restore">
  {#snippet children()}
    <div class="section">
      <div class="section-header">
        <h2 class="section-title">Download Backup</h2>
        <p class="section-desc">Download a complete copy of your SQLite database. Store it somewhere safe — this single file contains all your content, settings, and users.</p>
      </div>
      <button class="btn btn--primary" onclick={download} disabled={downloading}>
        {downloading ? 'Downloading…' : 'Download backup.sqlite'}
      </button>
    </div>

    <div class="divider"></div>

    <div class="section">
      <div class="section-header">
        <h2 class="section-title">Restore from Backup</h2>
        <p class="section-desc">Upload a previously downloaded <code>.sqlite</code> backup file. This will overwrite all current data. A snapshot of the current database is saved automatically before restore.</p>
      </div>

      {#if !pendingInfo}
        <label class="upload-zone" class:upload-zone--loading={uploading}>
          <input
            bind:this={fileInput}
            type="file"
            accept=".sqlite"
            onchange={onFileChange}
            style="display:none"
            disabled={uploading}
          />
          {#if uploading}
            <span class="upload-zone__label">Uploading…</span>
          {:else}
            <span class="upload-zone__icon">↑</span>
            <span class="upload-zone__label">Click to upload <code>.sqlite</code> file</span>
          {/if}
        </label>
      {:else}
        <div class="pending-card">
          <div class="pending-icon">✓</div>
          <div class="pending-info">
            <strong>Backup ready to restore</strong>
            <span class="pending-size">{(pendingInfo.size / 1024).toFixed(1)} KB</span>
          </div>
          <div class="pending-actions">
            <button class="btn btn--ghost" onclick={() => pendingInfo = null}>Cancel</button>
            <button class="btn btn--danger" onclick={() => confirming = true}>Restore now</button>
          </div>
        </div>
      {/if}
    </div>
  {/snippet}
</AdminShell>

<ConfirmDialog
  open={confirming}
  title="Restore backup?"
  message="This will replace all current data with the backup. This cannot be undone. Are you sure?"
  confirmLabel="Yes, restore"
  danger={true}
  onconfirm={confirmRestore}
  oncancel={() => confirming = false}
/>

<style>
  .section { max-width: 640px; }
  .section-header { margin-bottom: 20px; }
  .section-title { margin: 0 0 6px; font-size: 16px; font-weight: 700; color: var(--sc-text); }
  .section-desc { margin: 0; font-size: 13px; color: var(--sc-text-muted); line-height: 1.6; }
  .divider { border: none; border-top: 1px solid var(--sc-border); margin: 36px 0; max-width: 640px; }

  .btn { padding: 8px 18px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.3); color: var(--sc-danger); }
  .btn--danger:hover { background: rgba(248,113,113,.2); }

  .upload-zone {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; border: 2px dashed var(--sc-border); border-radius: var(--sc-radius-lg);
    padding: 40px; cursor: pointer; transition: border-color .15s, background .15s;
    text-align: center;
  }
  .upload-zone:hover { border-color: var(--sc-accent); background: rgba(var(--sc-accent-rgb),.03); }
  .upload-zone--loading { opacity: .6; pointer-events: none; }
  .upload-zone__icon { font-size: 28px; color: var(--sc-text-muted); }
  .upload-zone__label { font-size: 13px; color: var(--sc-text-muted); }
  .upload-zone__label code { background: var(--sc-surface-2); padding: 1px 5px; border-radius: 3px; font-size: 12px; }

  .pending-card { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); }
  .pending-icon { font-size: 20px; color: var(--sc-success); flex-shrink: 0; }
  .pending-info { flex: 1; display: flex; flex-direction: column; gap: 3px; font-size: 13px; }
  .pending-size { color: var(--sc-text-muted); font-size: 12px; }
  .pending-actions { display: flex; gap: 8px; flex-shrink: 0; }
</style>
