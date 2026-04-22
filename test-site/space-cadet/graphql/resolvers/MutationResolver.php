<?php
/**
 * Space Cadet CMS — GraphQL Mutation Resolver
 * Maps root mutation fields → model/controller logic.
 * All mutations require authenticated user (enforced by Validator).
 */
class MutationResolver {

    public function resolve(string $field, array $args, array $variables): mixed {
        return match($field) {
            'createCollectionItem' => $this->createCollectionItem($args, $variables),
            'updateCollectionItem' => $this->updateCollectionItem($args, $variables),
            'deleteCollectionItem' => $this->deleteCollectionItem($args, $variables),
            'createPage'           => $this->createPage($args, $variables),
            'updatePage'           => $this->updatePage($args, $variables),
            'deletePage'           => $this->deletePage($args, $variables),
            'submitForm'           => $this->submitForm($args, $variables),
            'updateMedia'          => $this->updateMedia($args, $variables),
            'deleteMedia'          => $this->deleteMedia($args, $variables),
            default                => null,
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

    private function inputObject(array $args, string $name, array $variables): array {
        // Input objects are passed as Variable referencing a variable map
        $v = $this->arg($args, $name, $variables);
        if (is_array($v)) return $v;
        return [];
    }

    // ── Collection Items ──────────────────────────────────────────────────────

    private function createCollectionItem(array $args, array $vars): ?array {
        $collectionId = (int)$this->arg($args, 'collectionId', $vars);
        if (!$collectionId) throw new RuntimeException('collectionId is required');
        $c = Collection::findById($collectionId);
        if (!$c) throw new RuntimeException('Collection not found');

        $title  = $this->arg($args, 'title', $vars, '');
        $status = $this->arg($args, 'status', $vars, 'draft');
        $fields = $this->arg($args, 'fields', $vars, []);

        $data = [
            'title'  => $title,
            'status' => $status,
            'slug'   => CollectionItem::uniqueSlug($collectionId, $title),
            'fields' => is_array($fields) ? $fields : [],
        ];

        $id = CollectionItem::create($collectionId, $data, Auth::userId() ?? 0);
        EventEmitter::emit('item.created', ['id' => $id, 'collection_id' => $collectionId]);
        return CollectionItem::findById($id);
    }

    private function updateCollectionItem(array $args, array $vars): ?array {
        $id = (int)$this->arg($args, 'id', $vars);
        if (!$id) throw new RuntimeException('id is required');
        CollectionItem::findById($id) ?? throw new RuntimeException('Item not found');

        $data = [];
        foreach (['title','status','slug','published_at'] as $k) {
            $v = $this->arg($args, $k, $vars, '__unset__');
            if ($v !== '__unset__') $data[$k] = $v;
        }
        $fields = $this->arg($args, 'fields', $vars, '__unset__');
        if ($fields !== '__unset__') $data['fields'] = is_array($fields) ? $fields : [];

        CollectionItem::update($id, $data);
        EventEmitter::emit('item.updated', ['id' => $id]);
        return CollectionItem::findById($id);
    }

    private function deleteCollectionItem(array $args, array $vars): array {
        $id = (int)$this->arg($args, 'id', $vars);
        if (!$id) throw new RuntimeException('id is required');
        $item = CollectionItem::findById($id) ?? throw new RuntimeException('Item not found');
        CollectionItem::delete($id);
        SearchIndex::remove('collection_item', $id);
        EventEmitter::emit('item.deleted', ['id' => $id]);
        return ['success' => true, 'id' => $id];
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    private function createPage(array $args, array $vars): ?array {
        $title = $this->arg($args, 'title', $vars, '');
        if (!$title) throw new RuntimeException('title is required');

        $data = [
            'title'     => $title,
            'slug'      => $this->arg($args, 'slug', $vars) ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)),
            'status'    => $this->arg($args, 'status', $vars, 'draft'),
            'parent_id' => $this->arg($args, 'parentId', $vars, null),
            'author_id' => Auth::userId(),
        ];

        $id = Page::create($data);
        SearchIndex::index('page', $id, $data['title'], '');
        EventEmitter::emit('page.created', ['id' => $id]);
        return Page::findById($id);
    }

    private function updatePage(array $args, array $vars): ?array {
        $id = (int)$this->arg($args, 'id', $vars);
        if (!$id) throw new RuntimeException('id is required');
        Page::findById($id) ?? throw new RuntimeException('Page not found');

        $data = [];
        foreach (['title','slug','status','meta_title','meta_desc','published_at'] as $k) {
            $v = $this->arg($args, $k, $vars, '__unset__');
            if ($v !== '__unset__') $data[$k] = $v;
        }

        Page::update($id, $data);
        $p = Page::findById($id);
        SearchIndex::index('page', $id, $p['title'], '');
        EventEmitter::emit('page.updated', ['id' => $id]);
        return $p;
    }

    private function deletePage(array $args, array $vars): array {
        $id = (int)$this->arg($args, 'id', $vars);
        if (!$id) throw new RuntimeException('id is required');
        Page::findById($id) ?? throw new RuntimeException('Page not found');
        Page::delete($id);
        SearchIndex::remove('page', $id);
        EventEmitter::emit('page.deleted', ['id' => $id]);
        return ['success' => true, 'id' => $id];
    }

    // ── Forms ─────────────────────────────────────────────────────────────────

    private function submitForm(array $args, array $vars): array {
        $formId = (int)$this->arg($args, 'formId', $vars);
        $form   = Form::findById($formId) ?? throw new RuntimeException('Form not found');
        $data   = $this->arg($args, 'data', $vars, []);

        // Honeypot check
        if (!empty($data[$form['honeypot_field'] ?? 'website'])) {
            return ['success' => true];  // silent discard
        }

        FormSubmission::create([
            'form_id'    => $formId,
            'data'       => is_array($data) ? $data : [],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        EventEmitter::emit('form.submitted', ['form_id' => $formId]);
        return ['success' => true, 'message' => $form['success_message'] ?? 'Thank you!'];
    }

    // ── Media ─────────────────────────────────────────────────────────────────

    private function updateMedia(array $args, array $vars): ?array {
        $id = (int)$this->arg($args, 'id', $vars);
        if (!$id) throw new RuntimeException('id is required');
        Media::findById($id) ?? throw new RuntimeException('Media not found');

        $data = [];
        foreach (['alt_text','caption','folder_id'] as $k) {
            $v = $this->arg($args, $k, $vars, '__unset__');
            if ($v !== '__unset__') $data[$k] = $v;
        }
        Media::update($id, $data);
        return Media::findById($id);
    }

    private function deleteMedia(array $args, array $vars): array {
        $id    = (int)$this->arg($args, 'id', $vars);
        $media = Media::delete($id) ?? throw new RuntimeException('Media not found');

        foreach ([$media['filename'], $media['webp_path'] ?? null] as $f) {
            if ($f && file_exists(SC_UPLOADS . '/' . $f)) unlink(SC_UPLOADS . '/' . $f);
        }
        if ($media['thumb_path'] && file_exists(SC_THUMBS . '/' . $media['thumb_path'])) {
            unlink(SC_THUMBS . '/' . $media['thumb_path']);
        }
        return ['success' => true, 'id' => $id];
    }
}
