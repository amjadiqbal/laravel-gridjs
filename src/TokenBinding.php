<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Who a grid token belongs to: "u:<id>" for a signed-in user, "s:<random>" for a guest's session
 * (a random value kept in the session, so the session ID itself never goes into a token), or null
 * when binding is off. With binding on and neither a user nor a session, no token is issued at all
 * (fail closed) unless 'allow_unbound_tokens' is set.
 */
class TokenBinding
{
    public const SESSION_KEY = 'gridjs.binding';

    /**
     * The binding for a token being rendered now. Creates the guest's session value if needed.
     */
    public static function forRender(): ?string
    {
        if (! config('gridjs.bind_tokens', true)) {
            return null;
        }

        if (($user = static::userBinding()) !== null) {
            return $user;
        }

        $session = request()->hasSession() ? request()->session() : null;
        if ($session === null) {
            if (static::unboundAllowed()) {
                return null;
            }

            throw new RuntimeException(
                'laravel-gridjs: cannot bind this grid\'s token: there is no signed-in user and no session. '
                .'Render grids on routes with the "web" middleware, or set gridjs.allow_unbound_tokens to true '
                .'(or gridjs.bind_tokens to false) to issue tokens anyone holding the URL can use until they expire.'
            );
        }

        $value = $session->get(self::SESSION_KEY);
        if (! is_string($value) || $value === '') {
            $value = Str::random(40);
            $session->put(self::SESSION_KEY, $value);
        }

        return 's:'.$value;
    }

    /**
     * Whether the current request may use a token carrying $binding.
     */
    public static function matches(?string $binding): bool
    {
        if ($binding === null) {
            // An unbound token is only accepted while unbound tokens are allowed.
            return static::unboundAllowed();
        }

        if (str_starts_with($binding, 'u:')) {
            return hash_equals($binding, (string) static::userBinding());
        }

        $session = request()->hasSession() ? request()->session() : null;
        $value = $session?->get(self::SESSION_KEY);

        return is_string($value) && $value !== '' && hash_equals($binding, 's:'.$value);
    }

    protected static function unboundAllowed(): bool
    {
        return ! config('gridjs.bind_tokens', true) || (bool) config('gridjs.allow_unbound_tokens', false);
    }

    protected static function userBinding(): ?string
    {
        $id = auth()->guard(config('gridjs.guard'))->id();

        return $id === null ? null : 'u:'.$id;
    }
}
