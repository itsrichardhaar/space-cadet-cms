<?php
/**
 * Space Cadet CMS — GraphQL Query Resolver
 * Maps root query fields → model calls.
 */
class QueryResolver {

    public function resolve(string $field, array $args, array $variables): mixed {
        return match($field) {
            'collections'    => $this->collections($args),
            'collection'     => $this->collection($args, $variables),
            'collectionItem' => $this->collectionItem($args, $variables),
            'pages'          => Page::all(),
            'page'           => $this->page($args, $variables),
            'globals'        => GlobalGroup::all(),
            'global'         => $this->global($args, $variables),
            'menus'          => Menu::all(),
            'menu'           => $this->menu($args, $variables),
            'media'          => $this->media($args, $variables),
            'mediaItem'      => $this->mediaItem($args, $variables),
            'users'          => $this->users($args),
            'user'           => $this->userById($args, $variables),
            'labels'         => $this->labels(),
            'folders'        => $this->folders(),
            'form'           => $this->form($args, $variables),
            'search'         => $this->search($args, $variables),
            default          => null,
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function arg(array $args, string $name, array $variables, mixed $default = null): mixed {
        foreach ($args as $a) {
            if ($a['name'] === $name) {
                $v = $a['value'];
                if ($v['kind'] === 'Variable') return $variables[$v['value']] ?? $default;
                return $v['value'];
            }
        }
        return $default;
    }

    // ── Collections ───────────────────────────────────────────────────────────

    private function collections(array $args): array {
        return Collection::withItemCount();
    }

    private function collection(array $args, array $vars): ?array {
        if ($slug = $this->arg($args, 'slug', $vars)) return Collection::findBySlug($slug);
        if ($id   = $this->arg($args, 'id',   $vars)) return Collection::findById((int)$id);
        return null;
    }

    private function collectionItem(array $args, array $vars): ?array {
        $collectionSlug = $this->arg($args, 'collection', $vars);
        if (!$collectionSlug) return null;
        $c = Collection::findBySlug($collectionSlug);
        if (!$c) return null;

        if ($itemId = $this->arg($args, 'id', $vars)) {
            return CollectionItem::findById((int)$itemId);
        }
        if ($slug = $this->arg($args, 'slug', $vars)) {
            return CollectionItem::findBySlug($c['id'], $slug);
        }
        return null;
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    private function page(array $args, array $vars): ?array {
        if ($slug = $this->arg($args, 'slug', $vars)) return Page::findBySlug($slug);
        if ($id   = $this->arg($args, 'id',   $vars)) return Page::findById((int)$id);
        return null;
    }

    // ── Globals ───────────────────────────────────────────────────────────────

    private function global(array $args, array $vars): ?array {
        if ($slug = $this->arg($args, 'slug', $vars)) return GlobalGroup::findBySlug($slug);
        if ($id   = $this->arg($args, 'id',   $vars)) return GlobalGroup::findById((int)$id);
        return null;
    }

    // ── Menus ─────────────────────────────────────────────────────────────────

    private function menu(array $args, array $vars): ?array {
        if ($slug = $this->arg($args, 'slug', $vars)) return Menu::findBySlug($slug);
        if ($id   = $this->arg($args, 'id',   $vars)) return Menu::findById((int)$id);
        return null;
    }

    // ── Media ─────────────────────────────────────────────────────────────────

    private function media(array $args, array $vars): array {
        $opts = [
            'folder_id' => $this->arg($args, 'folder_id', $vars),
            'mime'      => $this->arg($args, 'mime',      $vars),
            'q'         => $this->arg($args, 'q',         $vars),
            'page'      => (int)$this->arg($args, 'page',     $vars, 1),
            'per_page'  => min((int)$this->arg($args, 'per_page', $vars, 20), 100),
        ];
        $result = Media::list($opts);
        // Add computed URL fields
        return array_map(fn($m) => $this->enrichMedia($m), $result['rows']);
    }

    private function mediaItem(array $args, array $vars): ?array {
        $id = $this->arg($args, 'id', $vars);
        if (!$id) return null;
        $m = Media::findById((int)$id);
        return $m ? $this->enrichMedia($m) : null;
    }

    private function enrichMedia(array $m): array {
        $m['url']      = '/storage/uploads/' . $m['filename'];
        $m['thumb_url'] = $m['thumb_path'] ? '/storage/thumbnails/' . $m['thumb_path'] : null;
        $m['webp_url']  = $m['webp_path']  ? '/storage/uploads/' . $m['webp_path']  : null;
        return $m;
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    private function users(array $args): array {
        return Database::query("SELECT id,email,display_name,role,status,created_at FROM users ORDER BY created_at DESC");
    }

    private function userById(array $args, array $vars): ?array {
        $id = $this->arg($args, 'id', $vars);
        if (!$id) return null;
        $u = User::findById((int)$id);
        return $u ? User::sanitize($u) : null;
    }

    // ── Labels / Folders ──────────────────────────────────────────────────────

    private function labels(): array {
        return Database::query("SELECT * FROM labels ORDER BY name ASC");
    }

    private function folders(): array {
        return Database::query("SELECT * FROM folders ORDER BY name ASC");
    }

    // ── Forms ─────────────────────────────────────────────────────────────────

    private function form(array $args, array $vars): ?array {
        if ($slug = $this->arg($args, 'slug', $vars)) return Form::findBySlug($slug);
        if ($id   = $this->arg($args, 'id',   $vars)) return Form::findById((int)$id);
        return null;
    }

    // ── Search ────────────────────────────────────────────────────────────────

    private function search(array $args, array $vars): array {
        $q = $this->arg($args, 'q', $vars, '');
        if (!$q) return [];
        $types = $this->arg($args, 'types', $vars, null);
        return FullTextSearch::search($q, is_array($types) ? $types : null);
    }
}
