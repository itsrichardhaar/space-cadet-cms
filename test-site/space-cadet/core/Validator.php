<?php
/**
 * Space Cadet CMS — Declarative Input Validator
 */

class ValidationResult {
    public function __construct(
        private bool  $valid,
        private array $errors,
        private array $data
    ) {}

    public function isValid(): bool  { return $this->valid; }
    public function errors(): array  { return $this->errors; }
    public function validated(): array { return $this->data; }

    public function failOrReturn(): array {
        if (!$this->valid) {
            Response::validationError($this->errors);
        }
        return $this->data;
    }
}

class Validator {
    private static array $customRules = [];

    /**
     * Validate $data against $rules.
     *
     * Rules format: ['field' => 'required|string|max:255', ...]
     *               ['field' => ['required', 'email']]
     */
    public static function validate(array $data, array $rules): ValidationResult {
        $errors    = [];
        $validated = [];

        foreach ($rules as $field => $ruleDef) {
            $ruleList = is_string($ruleDef) ? explode('|', $ruleDef) : (array) $ruleDef;
            $value    = $data[$field] ?? null;
            $required = in_array('required', $ruleList, true);

            // Handle missing / null
            if ($value === null || $value === '') {
                if ($required) {
                    $errors[$field][] = "The {$field} field is required.";
                }
                continue;
            }

            $fieldErrors = [];
            foreach ($ruleList as $rule) {
                if ($rule === 'required') continue;

                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $error = self::applyRule($ruleName, $field, $value, $param, $data);
                if ($error !== null) {
                    $fieldErrors[] = $error;
                }
            }

            if (empty($fieldErrors)) {
                $validated[$field] = $value;
            } else {
                $errors[$field] = $fieldErrors;
            }
        }

        return new ValidationResult(empty($errors), $errors, $validated);
    }

    private static function applyRule(string $rule, string $field, mixed $value, ?string $param, array $data): ?string {
        return match($rule) {
            'string'  => !is_string($value)              ? "The {$field} must be a string."         : null,
            'integer' => !filter_var($value, FILTER_VALIDATE_INT) ? "The {$field} must be an integer." : null,
            'float'   => !is_numeric($value)             ? "The {$field} must be a number."          : null,
            'boolean' => !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)
                                                         ? "The {$field} must be boolean."           : null,
            'email'   => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "The {$field} must be a valid email." : null,
            'url'     => !filter_var($value, FILTER_VALIDATE_URL)   ? "The {$field} must be a valid URL."   : null,
            'min'     => (is_string($value) ? mb_strlen($value) : (int)$value) < (int)$param
                                                         ? "The {$field} must be at least {$param}." : null,
            'max'     => (is_string($value) ? mb_strlen($value) : (int)$value) > (int)$param
                                                         ? "The {$field} must not exceed {$param}."  : null,
            'in'      => !in_array($value, explode(',', $param ?? ''), true)
                                                         ? "The {$field} must be one of: {$param}."  : null,
            'regex'   => !preg_match($param ?? '/.*/', $value)
                                                         ? "The {$field} format is invalid."         : null,
            'slug'    => !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)
                                                         ? "The {$field} must be a valid slug (lowercase letters, numbers, hyphens)." : null,
            'unique'  => self::checkUnique($field, $value, $param, $data),
            'exists'  => self::checkExists($field, $value, $param),
            'json'    => (json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE)
                                                         ? "The {$field} must be valid JSON."        : null,
            default   => isset(self::$customRules[$rule])
                         ? (self::$customRules[$rule])($value, $param, $data, $field)
                         : null,
        };
    }

    private static function checkUnique(string $field, mixed $value, ?string $param, array $data): ?string {
        if (!$param) return null;
        [$table, $column] = array_pad(explode('.', $param), 2, $field);
        $excludeId = $data['id'] ?? null;
        $exists = Database::exists($table, $column, $value, $excludeId ? (int)$excludeId : null);
        return $exists ? "The {$field} is already taken." : null;
    }

    private static function checkExists(string $field, mixed $value, ?string $param): ?string {
        if (!$param) return null;
        [$table, $column] = array_pad(explode('.', $param), 2, 'id');
        $exists = Database::exists($table, $column, $value);
        return !$exists ? "The selected {$field} is invalid." : null;
    }

    public static function addRule(string $name, callable $fn): void {
        self::$customRules[$name] = $fn;
    }
}
