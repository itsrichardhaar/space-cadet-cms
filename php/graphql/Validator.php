<?php
/**
 * Space Cadet CMS — GraphQL AST Validator
 *
 * Enforces:
 *   - Max query size: 32 KB
 *   - Max field depth: 10
 *   - Max total selections: 500
 *   - Mutation operations require Bearer sc_ token
 */
class GQLValidator {

    const MAX_DEPTH      = 10;
    const MAX_SELECTIONS = 500;
    const MAX_SIZE       = 32768; // 32 KB

    private array  $errors     = [];
    private int    $selections = 0;
    private bool   $requireAuthForMutations;
    private bool   $isAuthenticated;

    public function __construct(bool $isAuthenticated = false) {
        $this->isAuthenticated = $isAuthenticated;
    }

    public static function validate(array $document, string $rawQuery, bool $isAuthenticated = false): array {
        $v = new self($isAuthenticated);

        // Size check
        if (strlen($rawQuery) > self::MAX_SIZE) {
            return ['errors' => [['message' => 'Query exceeds maximum allowed size (32KB)']]];
        }

        $v->validateDocument($document);

        return ['errors' => $v->errors];
    }

    private function validateDocument(array $doc): void {
        foreach ($doc['definitions'] as $def) {
            if ($def['kind'] === 'OperationDefinition') {
                $this->validateOperation($def);
            }
        }
    }

    private function validateOperation(array $op): void {
        // Mutations require authentication
        if ($op['operation'] === 'mutation' && !$this->isAuthenticated) {
            $this->errors[] = ['message' => 'Mutations require authentication (Bearer sc_ token)'];
            return;
        }

        $this->validateSelectionSet($op['selectionSet'], 1);
    }

    private function validateSelectionSet(array $ss, int $depth): void {
        if ($depth > self::MAX_DEPTH) {
            $this->errors[] = ['message' => "Query depth exceeds maximum of " . self::MAX_DEPTH];
            return;
        }

        foreach ($ss['selections'] as $sel) {
            $this->selections++;
            if ($this->selections > self::MAX_SELECTIONS) {
                $this->errors[] = ['message' => "Query exceeds maximum of " . self::MAX_SELECTIONS . " field selections"];
                return;
            }

            if ($sel['kind'] === 'Field' && $sel['selectionSet']) {
                $this->validateSelectionSet($sel['selectionSet'], $depth + 1);
            }
            if ($sel['kind'] === 'InlineFragment' && isset($sel['selectionSet'])) {
                $this->validateSelectionSet($sel['selectionSet'], $depth + 1);
            }
        }
    }
}
