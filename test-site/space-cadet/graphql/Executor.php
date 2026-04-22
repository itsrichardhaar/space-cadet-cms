<?php
/**
 * Space Cadet CMS — GraphQL Executor
 *
 * Walks the AST against resolved data and applies field selection.
 * Calls QueryResolver / MutationResolver for root fields.
 */
class GQLExecutor {

    private array $variables;
    private array $fragments;
    private QueryResolver   $queryResolver;
    private MutationResolver $mutationResolver;

    public function __construct(array $variables = []) {
        $this->variables        = $variables;
        $this->queryResolver    = new QueryResolver();
        $this->mutationResolver = new MutationResolver();
    }

    /**
     * Execute a parsed + validated document.
     * Returns ['data' => ..., 'errors' => [...]]
     */
    public function execute(array $document): array {
        // Collect fragment definitions
        $this->fragments = [];
        foreach ($document['definitions'] as $def) {
            if ($def['kind'] === 'FragmentDefinition') {
                $this->fragments[$def['name']] = $def;
            }
        }

        $data   = [];
        $errors = [];

        foreach ($document['definitions'] as $def) {
            if ($def['kind'] !== 'OperationDefinition') continue;

            $resolver = $def['operation'] === 'mutation'
                ? fn(string $f, array $a) => $this->mutationResolver->resolve($f, $a, $this->variables)
                : fn(string $f, array $a) => $this->queryResolver->resolve($f, $a, $this->variables);

            foreach ($def['selectionSet']['selections'] as $sel) {
                $key = $sel['alias'] ?? $sel['name'];

                if ($this->shouldSkip($sel)) {
                    $data[$key] = null;
                    continue;
                }

                try {
                    $raw = $resolver($sel['name'], $sel['arguments'] ?? []);
                    $data[$key] = $this->resolveField($raw, $sel);
                } catch (\Throwable $e) {
                    $errors[] = ['message' => $e->getMessage(), 'path' => [$key]];
                    $data[$key] = null;
                }
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    // ── Field Resolution ──────────────────────────────────────────────────────

    private function resolveField(mixed $value, array $fieldNode): mixed {
        if ($value === null) return null;

        // No sub-selection: return scalar
        if (!isset($fieldNode['selectionSet']) || !$fieldNode['selectionSet']) {
            return $value;
        }

        // Array of objects
        if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            return array_map(fn($item) => $this->applySelectionSet($item, $fieldNode['selectionSet']), $value);
        }

        // Paginated result (['rows' => [...], 'total' => n])
        if (is_array($value) && array_key_exists('rows', $value) && array_key_exists('total', $value)) {
            return array_map(fn($item) => $this->applySelectionSet($item, $fieldNode['selectionSet']), $value['rows']);
        }

        // Single object
        if (is_array($value)) {
            return $this->applySelectionSet($value, $fieldNode['selectionSet']);
        }

        return $value;
    }

    private function applySelectionSet(array $obj, array $ss): array {
        $result = [];

        foreach ($ss['selections'] as $sel) {
            if ($this->shouldSkip($sel)) continue;

            if ($sel['kind'] === 'FragmentSpread') {
                $frag = $this->fragments[$sel['name']] ?? null;
                if ($frag) {
                    $merged = $this->applySelectionSet($obj, $frag['selectionSet']);
                    $result = array_merge($result, $merged);
                }
                continue;
            }

            if ($sel['kind'] === 'InlineFragment') {
                $merged = $this->applySelectionSet($obj, $sel['selectionSet']);
                $result = array_merge($result, $merged);
                continue;
            }

            // Regular field
            $key      = $sel['alias'] ?? $sel['name'];
            $fieldName = $sel['name'];
            $rawValue  = $obj[$fieldName] ?? null;

            // Decode JSON strings for structured fields
            if (is_string($rawValue) && in_array($fieldName, ['fields','values','options','events','notify_emails','scopes'], true)) {
                $decoded = json_decode($rawValue, true);
                if (json_last_error() === JSON_ERROR_NONE) $rawValue = $decoded;
            }

            $result[$key] = $this->resolveField($rawValue, $sel);
        }

        return $result;
    }

    // ── Directive Handling ────────────────────────────────────────────────────

    private function shouldSkip(array $node): bool {
        foreach ($node['directives'] ?? [] as $dir) {
            if ($dir['name'] === 'skip') {
                foreach ($dir['arguments'] as $a) {
                    if ($a['name'] === 'if') {
                        $v = $a['value'];
                        $val = $v['kind'] === 'Variable' ? ($this->variables[$v['value']] ?? false) : $v['value'];
                        if ($val === true || $val === 'true') return true;
                    }
                }
            }
            if ($dir['name'] === 'include') {
                foreach ($dir['arguments'] as $a) {
                    if ($a['name'] === 'if') {
                        $v = $a['value'];
                        $val = $v['kind'] === 'Variable' ? ($this->variables[$v['value']] ?? true) : $v['value'];
                        if ($val === false || $val === 'false') return true;
                    }
                }
            }
        }
        return false;
    }
}
