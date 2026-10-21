<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Support\Str;

/**
 * Who a grid token belongs to: "u:<id>" for a signed-in user, "s:<random>" for a guest's session
 * (a random value kept in the session, so the session ID itself never goes into a token), or null
 * when binding is off or there is no session to bind to.
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
            return null;
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
            return true;
        }

        if (str_starts_with($binding, 'u:')) {
            return hash_equals($binding, (string) static::userBinding());
        }

        $session = request()->hasSession() ? request()->session() : null;
        $value = $session?->get(self::SESSION_KEY);

        return is_string($value) && $value !== '' && hash_equals($binding, 's:'.$value);
    }

    protected static function userBinding(): ?string
    {
        $id = auth()->guard(config('gridjs.guard'))->id();

        return $id === null ? null : 'u:'.$id;
    }
}
