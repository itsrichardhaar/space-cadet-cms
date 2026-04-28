<?php
/**
 * Space Cadet CMS — PHP Test Runner
 *
 * Usage: php ralph/test.php
 *
 * Each file in tests/ is included. Test functions call assert_*() helpers.
 * Exits 0 on all pass, 1 on any failure.
 */

declare(strict_types=1);

// ── Helpers ───────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$current_test = '(unknown)';

function it(string $description, callable $fn): void {
    global $passed, $failed, $current_test;
    $current_test = $description;
    try {
        $fn();
        $passed++;
        echo "\033[32m  ✓\033[0m {$description}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "\033[31m  ✗\033[0m {$description}\n";
        echo "    \033[31m{$e->getMessage()}\033[0m\n";
    }
}

function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $exp = json_encode($expected);
        $got = json_encode($actual);
        throw new RuntimeException($msg ?: "Expected {$exp}, got {$got}");
    }
}

function assert_true(mixed $value, string $msg = ''): void {
    if (!$value) {
        $got = json_encode($value);
        throw new RuntimeException($msg ?: "Expected truthy, got {$got}");
    }
}

function assert_false(mixed $value, string $msg = ''): void {
    if ($value) {
        $got = json_encode($value);
        throw new RuntimeException($msg ?: "Expected falsy, got {$got}");
    }
}

function assert_null(mixed $value, string $msg = ''): void {
    if ($value !== null) {
        $got = json_encode($value);
        throw new RuntimeException($msg ?: "Expected null, got {$got}");
    }
}

function assert_not_null(mixed $value, string $msg = ''): void {
    if ($value === null) {
        throw new RuntimeException($msg ?: "Expected non-null value");
    }
}

function assert_contains(string $needle, string $haystack, string $msg = ''): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($msg ?: "Expected string to contain: {$needle}");
    }
}

function assert_count(int $expected, array $array, string $msg = ''): void {
    $actual = count($array);
    if ($expected !== $actual) {
        throw new RuntimeException($msg ?: "Expected count {$expected}, got {$actual}");
    }
}

function assert_throws(string $exceptionClass, callable $fn, string $msg = ''): void {
    try {
        $fn();
        throw new RuntimeException($msg ?: "Expected {$exceptionClass} to be thrown, but nothing was");
    } catch (Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            throw new RuntimeException($msg ?: "Expected {$exceptionClass}, got " . get_class($e) . ": " . $e->getMessage());
        }
    }
}

// ── Load test files ───────────────────────────────────────────────────────────

$testDir = __DIR__ . '/../tests';
$files   = glob("{$testDir}/*.php") ?: [];

if (empty($files)) {
    echo "\033[33mNo test files found in tests/\033[0m\n";
    exit(0);
}

foreach ($files as $file) {
    $label = basename($file);
    echo "\n\033[1m{$label}\033[0m\n";
    require $file;
}

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n";
echo "\033[1m" . ($passed + $failed) . " tests\033[0m: ";
echo "\033[32m{$passed} passed\033[0m";
if ($failed > 0) {
    echo ", \033[31m{$failed} failed\033[0m";
}
echo "\n";

exit($failed > 0 ? 1 : 0);
