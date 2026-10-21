<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * Bound as a scoped singleton: one instance per request (and per Octane request), so the
 * "already printed the assets on this page" flags reset between requests.
 */
class GridManager
{
    protected bool $assetsRendered = false;

    protected bool $runtimeRendered = false;

    /**
     * @param  Builder<Model>  $query
     */
    public function fromQuery(Builder $query): GridBuilder
    {
        return new GridBuilder($query, $this);
    }

    /**
     * Script and stylesheet tags for Grid.js plus the small mount runtime. Safe to call more than
     * once: each part is printed once per request. $theme: null for the configured default,
     * false for no theme stylesheet.
     */
    public function assets(string|false|null $theme = null): HtmlString
    {
        $html = '';

        if (! $this->assetsRendered) {
            $this->assetsRendered = true;
            $html .= $this->libraryTags($theme === false ? null : ($theme ?? $this->defaultTheme()));
        }

        return new HtmlString($html.$this->runtime());
    }

    /**
     * What render() prints in front of a grid: the library tags when auto-injection is on, and
     * always the runtime (once).
     */
    public function assetsFor(?string $theme): string
    {
        $html = '';

        if (! $this->assetsRendered && config('gridjs.assets.inject', true)) {
            $this->assetsRendered = true;
            $html .= $this->libraryTags($theme);
        }

        return $html.$this->runtime();
    }

    public function defaultTheme(): ?string
    {
        $theme = config('gridjs.theme', 'mermaid');

        return is_string($theme) && $theme !== '' ? $theme : null;
    }

    public function nonceAttribute(): string
    {
        $nonce = app()->bound(Vite::class) ? app(Vite::class)->cspNonce() : null;

        return $nonce ? ' nonce="'.e($nonce).'"' : '';
    }

    protected function libraryTags(?string $theme): string
    {
        $nonce = $this->nonceAttribute();

        if (config('gridjs.assets.cdn', true)) {
            $tags = '';
            $css = $theme ? config("gridjs.assets.themes.{$theme}") : null;
            if (is_array($css) && isset($css['url'])) {
                $tags .= '<link rel="stylesheet" href="'.e($css['url']).'"'.$this->integrity($css['integrity'] ?? null).'>';
            }
            $tags .= '<script src="'.e((string) config('gridjs.assets.script')).'"'
                .$this->integrity(config('gridjs.assets.script_integrity')).$nonce.'></script>';

            return $tags;
        }

        $base = rtrim((string) config('gridjs.assets.local_url', '/vendor/gridjs'), '/');
        $tags = $theme === 'mermaid' ? '<link rel="stylesheet" href="'.e(asset($base.'/mermaid.min.css')).'">' : '';

        return $tags.'<script src="'.e(asset($base.'/gridjs.umd.js')).'"'.$nonce.'></script>';
    }

    protected function integrity(mixed $hash): string
    {
        return is_string($hash) && $hash !== '' ? ' integrity="'.e($hash).'" crossorigin="anonymous"' : '';
    }

    protected function runtime(): string
    {
        if ($this->runtimeRendered) {
            return '';
        }
        $this->runtimeRendered = true;

        return '<script'.$this->nonceAttribute().'>'.trim((string) file_get_contents(__DIR__.'/../resources/js/runtime.js')).'</script>';
    }
}
