<?php
/**
 * Space Cadet CMS — GraphQL Entry Point
 *
 * Pipeline: raw body → GQLParser → GQLValidator → GQLExecutor → JSON response
 * Autoloads Parser, Validator, Schema, Executor, QueryResolver, MutationResolver.
 */

class GraphQL {

    public static function handle(Request $req): void {
        // Load GraphQL engine classes
        $base = __DIR__;
        require_once $base . '/Parser.php';
        require_once $base . '/Validator.php';
        require_once $base . '/Schema.php';
        require_once $base . '/Executor.php';
        require_once $base . '/resolvers/QueryResolver.php';
        require_once $base . '/resolvers/MutationResolver.php';

        $body      = $req->json() ?? [];
        $query     = trim($body['query'] ?? '');
        $variables = $body['variables'] ?? [];
        $opName    = $body['operationName'] ?? null;

        if (empty($query)) {
            Response::json(['data' => null, 'errors' => [['message' => 'GraphQL query is required']]], 400);
            return;
        }

        // Size guard (also checked in Validator, but fail-fast before parse)
        if (strlen($query) > GQLValidator::MAX_SIZE) {
            Response::json(['data' => null, 'errors' => [['message' => 'Query exceeds maximum allowed size (32KB)']]], 413);
            return;
        }

        // ── Parse ────────────────────────────────────────────────────────────
        try {
            $document = GQLParser::parse($query);
        } catch (\Throwable $e) {
            Response::json(['data' => null, 'errors' => [['message' => 'Parse error: ' . $e->getMessage()]]], 400);
            return;
        }

        // ── Validate ─────────────────────────────────────────────────────────
        $isAuth  = Auth::check();
        $valResult = GQLValidator::validate($document, $query, $isAuth);
        if (!empty($valResult['errors'])) {
            $status = self::errorsToStatus($valResult['errors']);
            Response::json(['data' => null, 'errors' => $valResult['errors']], $status);
            return;
        }

        // ── Coerce variables ─────────────────────────────────────────────────
        if (!is_array($variables)) $variables = [];

        // ── Execute ──────────────────────────────────────────────────────────
        try {
            $executor = new GQLExecutor($variables);
            $result   = $executor->execute($document);
        } catch (\Throwable $e) {
            Response::json(['data' => null, 'errors' => [['message' => 'Execution error: ' . $e->getMessage()]]], 500);
            return;
        }

        // GraphQL spec: omit errors key when empty, omit data key when null and errors present
        $payload = ['data' => $result['data']];
        if (!empty($result['errors'])) {
            $payload['errors'] = $result['errors'];
        }

        Response::json($payload);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function errorsToStatus(array $errors): int {
        foreach ($errors as $e) {
            $msg = strtolower($e['message'] ?? '');
            if (str_contains($msg, 'authentication') || str_contains($msg, 'auth')) return 401;
            if (str_contains($msg, 'maximum allowed size') || str_contains($msg, 'exceeds')) return 413;
        }
        return 400;
    }
}
