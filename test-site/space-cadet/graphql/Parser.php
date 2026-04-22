<?php
/**
 * Space Cadet CMS — Hand-Written GraphQL Parser
 * Tokenizes and parses a GraphQL document into an AST array.
 *
 * Supported subset:
 *   - anonymous queries / named query / mutation operations
 *   - fields, nested selection sets, aliases (alias: field)
 *   - arguments with scalar values (string, int, float, boolean, null, enum, variable $var)
 *   - inline fragments (... on Type { })
 *   - fragment definitions and spreads (...FragName)
 *   - @skip / @include directives
 *   - variables with defaults
 */
class GQLParser {

    private string $src;
    private int    $pos = 0;
    private int    $len;

    public function __construct(string $src) {
        $this->src = $src;
        $this->len = strlen($src);
    }

    // ── Public entry ──────────────────────────────────────────────────────────

    public static function parse(string $src): array {
        $p = new self($src);
        return $p->parseDocument();
    }

    // ── Document / Operations ─────────────────────────────────────────────────

    private function parseDocument(): array {
        $definitions = [];
        $this->skipIgnored();
        while ($this->pos < $this->len) {
            $definitions[] = $this->parseDefinition();
            $this->skipIgnored();
        }
        return ['kind' => 'Document', 'definitions' => $definitions];
    }

    private function parseDefinition(): array {
        $keyword = $this->peekWord();

        if ($keyword === 'fragment') {
            return $this->parseFragmentDefinition();
        }

        // operation: query | mutation | subscription | anonymous {
        $op = 'query';
        $name = null;
        $varDefs = [];

        if ($keyword === 'query' || $keyword === 'mutation' || $keyword === 'subscription') {
            $op = $this->parseWord();
            $this->skipIgnored();
            // optional name
            if ($this->pos < $this->len && ctype_alpha($this->src[$this->pos])) {
                $name = $this->parseWord();
                $this->skipIgnored();
            }
            // optional variable definitions
            if ($this->pos < $this->len && $this->src[$this->pos] === '(') {
                $varDefs = $this->parseVariableDefinitions();
                $this->skipIgnored();
            }
        }

        $selectionSet = $this->parseSelectionSet();

        return [
            'kind'                => 'OperationDefinition',
            'operation'           => $op,
            'name'                => $name,
            'variableDefinitions' => $varDefs,
            'selectionSet'        => $selectionSet,
        ];
    }

    private function parseFragmentDefinition(): array {
        $this->parseWord(); // consume 'fragment'
        $this->skipIgnored();
        $name = $this->parseWord();
        $this->skipIgnored();
        $this->expect('on');
        $this->skipIgnored();
        $typeCondition = $this->parseWord();
        $this->skipIgnored();
        $selectionSet = $this->parseSelectionSet();
        return ['kind' => 'FragmentDefinition', 'name' => $name, 'typeCondition' => $typeCondition, 'selectionSet' => $selectionSet];
    }

    // ── Variable Definitions ──────────────────────────────────────────────────

    private function parseVariableDefinitions(): array {
        $this->consume('(');
        $defs = [];
        $this->skipIgnored();
        while ($this->pos < $this->len && $this->src[$this->pos] !== ')') {
            $this->consume('$');
            $varName = $this->parseWord();
            $this->skipIgnored();
            $this->consume(':');
            $this->skipIgnored();
            $type = $this->parseTypeRef();
            $this->skipIgnored();
            $default = null;
            if ($this->pos < $this->len && $this->src[$this->pos] === '=') {
                $this->consume('=');
                $this->skipIgnored();
                $default = $this->parseValue();
            }
            $defs[] = ['variable' => $varName, 'type' => $type, 'default' => $default];
            $this->skipIgnored();
            if ($this->pos < $this->len && $this->src[$this->pos] === ',') {
                $this->pos++;
                $this->skipIgnored();
            }
        }
        $this->consume(')');
        return $defs;
    }

    private function parseTypeRef(): string {
        $name = '';
        if ($this->pos < $this->len && $this->src[$this->pos] === '[') {
            $this->pos++;
            $inner = $this->parseTypeRef();
            $this->consume(']');
            $name = "[$inner]";
        } else {
            $name = $this->parseWord();
        }
        if ($this->pos < $this->len && $this->src[$this->pos] === '!') {
            $this->pos++;
            $name .= '!';
        }
        return $name;
    }

    // ── Selection Set ─────────────────────────────────────────────────────────

    private function parseSelectionSet(): array {
        $this->consume('{');
        $selections = [];
        $this->skipIgnored();
        while ($this->pos < $this->len && $this->src[$this->pos] !== '}') {
            $selections[] = $this->parseSelection();
            $this->skipIgnored();
            if ($this->pos < $this->len && $this->src[$this->pos] === ',') {
                $this->pos++;
                $this->skipIgnored();
            }
        }
        $this->consume('}');
        return ['kind' => 'SelectionSet', 'selections' => $selections];
    }

    private function parseSelection(): array {
        // Inline fragment or fragment spread
        if ($this->pos + 2 < $this->len && substr($this->src, $this->pos, 3) === '...') {
            $this->pos += 3;
            $this->skipIgnored();
            if ($this->peekWord() === 'on') {
                // inline fragment
                $this->parseWord(); // consume 'on'
                $this->skipIgnored();
                $typeCondition = $this->parseWord();
                $this->skipIgnored();
                $directives = $this->parseDirectives();
                $selectionSet = $this->parseSelectionSet();
                return ['kind' => 'InlineFragment', 'typeCondition' => $typeCondition, 'directives' => $directives, 'selectionSet' => $selectionSet];
            } else {
                // fragment spread
                $name = $this->parseWord();
                $this->skipIgnored();
                $directives = $this->parseDirectives();
                return ['kind' => 'FragmentSpread', 'name' => $name, 'directives' => $directives];
            }
        }

        // Field (possibly aliased)
        $nameOrAlias = $this->parseWord();
        $this->skipIgnored();

        $alias = null;
        $name  = $nameOrAlias;

        if ($this->pos < $this->len && $this->src[$this->pos] === ':') {
            $this->pos++; // consume ':'
            $this->skipIgnored();
            $alias = $nameOrAlias;
            $name  = $this->parseWord();
            $this->skipIgnored();
        }

        $args = [];
        if ($this->pos < $this->len && $this->src[$this->pos] === '(') {
            $args = $this->parseArguments();
            $this->skipIgnored();
        }

        $directives = $this->parseDirectives();

        $selectionSet = null;
        if ($this->pos < $this->len && $this->src[$this->pos] === '{') {
            $selectionSet = $this->parseSelectionSet();
        }

        return [
            'kind'         => 'Field',
            'alias'        => $alias,
            'name'         => $name,
            'arguments'    => $args,
            'directives'   => $directives,
            'selectionSet' => $selectionSet,
        ];
    }

    // ── Arguments ─────────────────────────────────────────────────────────────

    private function parseArguments(): array {
        $this->consume('(');
        $args = [];
        $this->skipIgnored();
        while ($this->pos < $this->len && $this->src[$this->pos] !== ')') {
            $name = $this->parseWord();
            $this->skipIgnored();
            $this->consume(':');
            $this->skipIgnored();
            $value = $this->parseValue();
            $args[] = ['name' => $name, 'value' => $value];
            $this->skipIgnored();
            if ($this->pos < $this->len && $this->src[$this->pos] === ',') {
                $this->pos++;
                $this->skipIgnored();
            }
        }
        $this->consume(')');
        return $args;
    }

    // ── Directives ────────────────────────────────────────────────────────────

    private function parseDirectives(): array {
        $dirs = [];
        while ($this->pos < $this->len && $this->src[$this->pos] === '@') {
            $this->pos++;
            $name = $this->parseWord();
            $this->skipIgnored();
            $args = [];
            if ($this->pos < $this->len && $this->src[$this->pos] === '(') {
                $args = $this->parseArguments();
                $this->skipIgnored();
            }
            $dirs[] = ['name' => $name, 'arguments' => $args];
        }
        return $dirs;
    }

    // ── Values ────────────────────────────────────────────────────────────────

    private function parseValue(): array {
        $ch = $this->src[$this->pos];

        // Variable
        if ($ch === '$') {
            $this->pos++;
            $name = $this->parseWord();
            return ['kind' => 'Variable', 'value' => $name];
        }

        // String
        if ($ch === '"') {
            return ['kind' => 'StringValue', 'value' => $this->parseStringLiteral()];
        }

        // Block string
        if (substr($this->src, $this->pos, 3) === '"""') {
            return ['kind' => 'StringValue', 'value' => $this->parseBlockString()];
        }

        // Number
        if ($ch === '-' || ctype_digit($ch)) {
            return $this->parseNumberValue();
        }

        // Boolean / null / enum
        $word = $this->parseWord();
        if ($word === 'true')  return ['kind' => 'BooleanValue', 'value' => true];
        if ($word === 'false') return ['kind' => 'BooleanValue', 'value' => false];
        if ($word === 'null')  return ['kind' => 'NullValue',    'value' => null];
        return ['kind' => 'EnumValue', 'value' => $word];
    }

    private function parseStringLiteral(): string {
        $this->pos++; // skip opening "
        $out = '';
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            if ($ch === '"') { $this->pos++; break; }
            if ($ch === '\\') {
                $this->pos++;
                $esc = $this->src[$this->pos++] ?? '';
                $out .= match($esc) {
                    'n'  => "\n", 't' => "\t", 'r' => "\r",
                    '"'  => '"',  '\\' => '\\', '/' => '/',
                    'b'  => "\x08", 'f' => "\x0C",
                    'u'  => $this->parseUnicode(),
                    default => $esc,
                };
            } else {
                $out .= $ch;
                $this->pos++;
            }
        }
        return $out;
    }

    private function parseUnicode(): string {
        $hex = substr($this->src, $this->pos, 4);
        $this->pos += 4;
        return mb_chr(hexdec($hex), 'UTF-8') ?: '';
    }

    private function parseBlockString(): string {
        $this->pos += 3; // skip """
        $end = strpos($this->src, '"""', $this->pos);
        if ($end === false) throw new RuntimeException('Unterminated block string');
        $raw = substr($this->src, $this->pos, $end - $this->pos);
        $this->pos = $end + 3;
        return trim($raw);
    }

    private function parseNumberValue(): array {
        $start = $this->pos;
        if ($this->src[$this->pos] === '-') $this->pos++;
        while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) $this->pos++;
        $isFloat = false;
        if ($this->pos < $this->len && $this->src[$this->pos] === '.') {
            $isFloat = true;
            $this->pos++;
            while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) $this->pos++;
        }
        if ($this->pos < $this->len && ($this->src[$this->pos] === 'e' || $this->src[$this->pos] === 'E')) {
            $isFloat = true;
            $this->pos++;
            if ($this->pos < $this->len && ($this->src[$this->pos] === '+' || $this->src[$this->pos] === '-')) $this->pos++;
            while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) $this->pos++;
        }
        $raw = substr($this->src, $start, $this->pos - $start);
        return $isFloat
            ? ['kind' => 'FloatValue',   'value' => (float) $raw]
            : ['kind' => 'IntValue',     'value' => (int)   $raw];
    }

    // ── Primitives ────────────────────────────────────────────────────────────

    private function parseWord(): string {
        $start = $this->pos;
        while ($this->pos < $this->len && (ctype_alnum($this->src[$this->pos]) || $this->src[$this->pos] === '_')) {
            $this->pos++;
        }
        if ($this->pos === $start) {
            throw new RuntimeException("Expected identifier at position {$this->pos}, got '" . ($this->src[$this->pos] ?? 'EOF') . "'");
        }
        return substr($this->src, $start, $this->pos - $start);
    }

    private function peekWord(): string {
        $save = $this->pos;
        try { return $this->parseWord(); } catch (\Throwable $e) { return ''; } finally { $this->pos = $save; }
    }

    private function consume(string $expected): void {
        $len = strlen($expected);
        if (substr($this->src, $this->pos, $len) !== $expected) {
            throw new RuntimeException("Expected '{$expected}' at position {$this->pos}, got '" . substr($this->src, $this->pos, $len) . "'");
        }
        $this->pos += $len;
    }

    private function expect(string $keyword): void {
        $word = $this->parseWord();
        if ($word !== $keyword) {
            throw new RuntimeException("Expected keyword '{$keyword}', got '{$word}'");
        }
    }

    private function skipIgnored(): void {
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];
            // Whitespace
            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === ',') {
                $this->pos++;
                continue;
            }
            // Comment
            if ($ch === '#') {
                while ($this->pos < $this->len && $this->src[$this->pos] !== "\n") $this->pos++;
                continue;
            }
            break;
        }
    }
}
