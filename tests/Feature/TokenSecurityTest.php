<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;
use AmjadIqbal\GridJS\TokenBinding;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
});

function usersGrid()
{
    return Grid::fromQuery(User::query()->orderBy('id'))->columns(['name'])->pagination(10);
}

function gridUrl(string $token): string
{
    return route('gridjs.data', ['grid' => $token]);
}

it('binds a token to the signed-in user who rendered it', function () {
    $this->actingAs($this->alice);
    $token = usersGrid()->token();

    $this->getJson(gridUrl($token))->assertOk()->assertJsonPath('total', 2);

    $this->actingAs($this->bob);
    $this->getJson(gridUrl($token))->assertForbidden();
});

it('refuses a user-bound token once the user has signed out', function () {
    $this->actingAs($this->alice);
    $token = usersGrid()->token();

    auth()->logout();

    $this->getJson(gridUrl($token))->assertForbidden();
});

it('binds a guest token to the browser session', function () {
    $session = app('session')->driver();
    $session->start();
    request()->setLaravelSession($session);

    $token = usersGrid()->token();
    $binding = $session->get(TokenBinding::SESSION_KEY);
    expect($binding)->toBeString()->toHaveLength(40);

    $this->withSession([TokenBinding::SESSION_KEY => $binding])->getJson(gridUrl($token))->assertOk();
    $this->withSession([TokenBinding::SESSION_KEY => 'another-browser'])->getJson(gridUrl($token))->assertForbidden();

    $session->forget(TokenBinding::SESSION_KEY);
    $this->getJson(gridUrl($token))->assertForbidden();
});

it('reuses the guest session value for every grid on the page', function () {
    $session = app('session')->driver();
    $session->start();
    request()->setLaravelSession($session);

    usersGrid()->token();
    $first = $session->get(TokenBinding::SESSION_KEY);
    usersGrid()->token();

    expect($session->get(TokenBinding::SESSION_KEY))->toBe($first);
});

it('expires tokens after the configured lifetime', function () {
    config(['gridjs.token_lifetime' => 30]);
    $token = usersGrid()->token();

    $this->travel(29)->minutes();
    $this->getJson(gridUrl($token))->assertOk();

    $this->travel(2)->minutes();
    $this->getJson(gridUrl($token))->assertForbidden();
});

it('can turn binding off for stateless setups', function () {
    config(['gridjs.bind_tokens' => false]);
    $this->actingAs($this->alice);
    $token = usersGrid()->token();

    $this->actingAs($this->bob);
    $this->getJson(gridUrl($token))->assertOk();
});

it('never sends hidden columns to the browser', function () {
    $html = (string) Grid::fromQuery(User::query()->orderBy('id'))
        ->columns(['name', ['field' => 'email', 'hidden' => true]])
        ->searchable()
        ->sortable()
        ->pagination(10)
        ->render();
    preg_match('/grid=([^"&]+)/', $html, $m);

    expect($html)->not->toContain('"email"');
    $json = $this->getJson(gridUrl(urldecode($m[1])))->assertOk()->json();
    expect($json['data'])->toBe([['Alice', null], ['Bob', null]])
        ->and(json_encode($json))->not->toContain('@example.com');

    // Search can't be used to probe a hidden column either.
    $this->getJson(gridUrl(urldecode($m[1])).'&search=example')->assertOk()->assertJsonPath('total', 0);
});

it('still sends a hidden column that fills an action link', function () {
    $json = $this->getJson(gridUrl(usersGrid()->actions([['label' => 'Edit', 'url' => '/u/{id}']])->token()))->json();

    expect($json['data'][0])->toBe(['Alice', null, 1]);
});

it('refuses to make a hidden column searchable or sortable', function () {
    expect(fn () => Grid::fromQuery(User::query())->columns(['name', ['field' => 'email', 'hidden' => true, 'searchable' => true]])->render())
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => Grid::fromQuery(User::query())->columns(['name', ['field' => 'email', 'hidden' => true, 'sortable' => true]])->render())
        ->toThrow(InvalidArgumentException::class);
});

function withoutSession(): void
{
    app()->instance('request', Request::create('/'));
}

it('refuses to issue a token with no user and no session (fail closed)', function () {
    withoutSession();

    expect(fn () => usersGrid()->token())->toThrow(RuntimeException::class, 'web');
    expect(fn () => (string) usersGrid()->render())->toThrow(RuntimeException::class);
});

it('still binds to the user when there is no session', function () {
    withoutSession();
    $this->actingAs($this->alice);
    $token = usersGrid()->token();

    $this->getJson(gridUrl($token))->assertOk();
    $this->actingAs($this->bob);
    $this->getJson(gridUrl($token))->assertForbidden();
});

it('issues unbound tokens only when allow_unbound_tokens is on', function () {
    withoutSession();
    config(['gridjs.allow_unbound_tokens' => true]);
    $token = usersGrid()->token();

    $this->getJson(gridUrl($token))->assertOk();

    // The same unbound token is refused once unbound tokens are no longer allowed.
    config(['gridjs.allow_unbound_tokens' => false]);
    $this->getJson(gridUrl($token))->assertForbidden();
});
