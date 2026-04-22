<?php
/**
 * Space Cadet CMS — Internal Synchronous Event Bus
 *
 * Controllers emit events (e.g. 'item.created') and listeners react
 * (webhook dispatch, search index updates, audit log writes).
 */

class EventEmitter {
    private static array $listeners = [];

    /**
     * Register a listener for one or more events.
     *
     * @param string|string[] $event  Event name(s) or '*' for all events
     * @param callable $listener      fn(string $event, array $payload): void
     */
    public static function on(string|array $event, callable $listener): void {
        foreach ((array) $event as $e) {
            self::$listeners[$e][] = $listener;
        }
    }

    /**
     * Fire an event and call all registered listeners.
     */
    public static function emit(string $event, array $payload = []): void {
        // Specific listeners
        foreach (self::$listeners[$event] ?? [] as $listener) {
            try {
                $listener($event, $payload);
            } catch (Throwable) {
                // Never let a listener crash the request
            }
        }

        // Wildcard listeners
        foreach (self::$listeners['*'] ?? [] as $listener) {
            try {
                $listener($event, $payload);
            } catch (Throwable) {
                // ignore
            }
        }
    }

    /**
     * Remove all listeners (useful in tests).
     */
    public static function reset(): void {
        self::$listeners = [];
    }

    /**
     * List registered event names.
     *
     * @return string[]
     */
    public static function events(): array {
        return array_keys(self::$listeners);
    }
}
