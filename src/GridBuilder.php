<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class GridBuilder
{
    protected ?string $modelClass = null;
    protected array $columns = [];
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected bool $resizable = false;
    protected bool $fixedHeader = false;
    protected ?int $paginationLimit = null;
    protected ?string $theme = null;
    protected ?array $customClassName = null;
    protected static bool $assetsInjected = false;

    public function __construct(Builder $query)
    {
        $this->modelClass = get_class($query->getModel());
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function sortable(bool $enabled = true): self
    {
        $this->sortable = $enabled;
        return $this;
    }

    public function searchable(bool $enabled = true): self
    {
        $this->searchable = $enabled;
        return $this;
    }

    public function resizable(bool $enabled = true): self
    {
        $this->resizable = $enabled;
        return $this;
    }

    public function fixedHeader(bool $enabled = true): self
    {
        $this->fixedHeader = $enabled;
        return $this;
    }

    public function pagination(int $limit): self
    {
        $this->paginationLimit = $limit;
        return $this;
    }

    public function theme(string|array $theme): self
    {
        if (is_string($theme)) {
            $this->theme = $theme;
            $this->customClassName = null;
        } else {
            $this->customClassName = $theme;
            $this->theme = null;
        }
        return $this;
    }

    public function render(): HtmlString
    {
        $containerId = 'gridjs_' . Str::random(10);
        $config = new GridConfig(
            columns: $this->extractHeaderLabels(),
            sortable: $this->sortable,
            searchable: $this->searchable,
            paginationLimit: $this->paginationLimit,
            resizable: $this->resizable,
            fixedHeader: $this->fixedHeader,
            className: $this->customClassName
        );

        $serverUrl = URL::route('gridjs.data', [
            'model' => $this->modelClass,
            'columns' => implode(',', $this->extractServerColumns()),
        ], false);

        $cssLinks = '';
        $cdn = (bool) config('gridjs.assets.cdn', true);
        $themes = (array) config('gridjs.assets.themes', []);
        $local = (array) config('gridjs.assets.local', []);
        if (!static::$assetsInjected) {
            if ($cdn) {
                if ($this->theme === 'mermaid' && isset($themes['mermaid'])) {
                    $cssLinks .= '<link rel="stylesheet" href="' . $themes['mermaid'] . '">';
                } elseif ($this->theme === 'skeleton' && isset($themes['skeleton'])) {
                    $cssLinks .= '<link rel="stylesheet" href="' . $themes['skeleton'] . '">';
                }
            } else {
                $publicUrl = (string) ($local['public_url'] ?? '/vendor/gridjs');
                $localThemes = (array) ($local['themes'] ?? []);
                if ($this->theme === 'mermaid' && isset($localThemes['mermaid'])) {
                    $cssLinks .= '<link rel="stylesheet" href="' . rtrim($publicUrl, '/') . '/' . $localThemes['mermaid'] . '">';
                } elseif ($this->theme === 'skeleton' && isset($localThemes['skeleton'])) {
                    $cssLinks .= '<link rel="stylesheet" href="' . rtrim($publicUrl, '/') . '/' . $localThemes['skeleton'] . '">';
                }
            }
        }

        $configJson = $config->toJson();
        $columnsJs = $this->buildColumnsJs();
        $scriptTag = '';
        $scriptUrl = (string) config('gridjs.assets.script', 'https://unpkg.com/gridjs/dist/gridjs.umd.js');
        if (!static::$assetsInjected) {
            if ($cdn && $scriptUrl) {
                $scriptTag = '<script src="' . $scriptUrl . '"></script>';
            } else {
                $publicUrl = (string) ($local['public_url'] ?? '/vendor/gridjs');
                $localScript = (string) ($local['script'] ?? 'gridjs.umd.js');
                $scriptTag = '<script src="' . rtrim($publicUrl, '/') . '/' . $localScript . '"></script>';
            }
        }
        static::$assetsInjected = true;
        $js = <<<JS
<div id="{$containerId}"></div>
{$cssLinks}
{$scriptTag}
<script>
const baseConfig = {$configJson};
{$columnsJs}
baseConfig.server = {
  url: '{$serverUrl}',
  method: 'GET',
  then: data => data.data,
  total: data => data.total
};
new gridjs.Grid(baseConfig).render(document.getElementById('{$containerId}'));
</script>
JS;
        return new HtmlString($js);
    }

    public static function resetAssetsInjection(): void
    {
        static::$assetsInjected = false;
    }

    protected function extractServerColumns(): array
    {
        $cols = [];
        foreach ($this->columns as $col) {
            if (is_array($col)) {
                $cols[] = $col['field'] ?? $col['name'] ?? (string)reset($col);
            } else {
                $cols[] = $col;
            }
        }
        return array_values(array_filter($cols, fn ($c) => is_string($c) && $c !== ''));
    }

    protected function extractHeaderLabels(): array
    {
        $labels = [];
        foreach ($this->columns as $col) {
            if (is_array($col)) {
                $labels[] = $col['name'] ?? $col['label'] ?? ($col['field'] ?? 'column');
            } else {
                $labels[] = $col;
            }
        }
        return $labels;
    }

    protected function buildColumnsJs(): string
    {
        $needsObjects = false;
        foreach ($this->columns as $col) {
            if (is_array($col)) {
                $needsObjects = true;
                break;
            }
        }
        if (!$needsObjects) {
            return '';
        }
        $items = [];
        foreach ($this->columns as $col) {
            if (!is_array($col)) {
                $items[] = "'" . addslashes($col) . "'";
                continue;
            }
            $name = addslashes($col['name'] ?? $col['label'] ?? ($col['field'] ?? 'column'));
            $formatter = $col['formatter'] ?? null;
            if (!$formatter && isset($col['format']) && is_array($col['format'])) {
                $formatter = $this->resolveFormatToFormatter($col['format']);
            }
            if (is_string($formatter)) {
                $items[] = "{name: '{$name}', formatter: {$formatter}}";
            } else {
                $items[] = "{name: '{$name}'}";
            }
        }
        $arrayJs = '[' . implode(',', $items) . ']';
        return "baseConfig.columns = {$arrayJs};";
    }

    protected function resolveFormatToFormatter(array $format): ?string
    {
        $type = $format['type'] ?? null;
        if ($type === 'date') {
            $locale = addslashes($format['locale'] ?? 'en-US');
            $options = json_encode($format['options'] ?? ['year' => 'numeric', 'month' => 'short', 'day' => '2-digit']);
            return "cell => { try { const d = new Date(cell); return d.toLocaleDateString('{$locale}', {$options}); } catch(e) { return cell; } }";
        }
        if ($type === 'currency') {
            $locale = addslashes($format['locale'] ?? 'en-US');
            $currency = addslashes($format['currency'] ?? 'USD');
            return "cell => { try { return new Intl.NumberFormat('{$locale}', { style: 'currency', currency: '{$currency}' }).format(Number(cell)); } catch(e) { return cell; } }";
        }
        if ($type === 'link') {
            $base = addslashes($format['baseUrl'] ?? '');
            $target = addslashes($format['target'] ?? '_self');
            if ($base !== '') {
                return "cell => gridjs.html('<a href=\"{$base}/' + cell + '\" target=\"{$target}\">' + cell + '</a>')";
            }
            return "cell => gridjs.html('<a href=\"' + cell + '\" target=\"{$target}\">' + cell + '</a>')";
        }
        return null;
    }

    public function actions(array $buttons, int $idIndex = 0): self
    {
        $parts = [];
        foreach ($buttons as $btn) {
            $label = addslashes($btn['label'] ?? 'Action');
            $base = addslashes($btn['baseUrl'] ?? '#');
            $class = addslashes($btn['class'] ?? 'btn btn-xs');
            $parts[] = "'<a href=\"" . $base . "/' + row.cells[" . $idIndex . "].data + '\" class=\"" . $class . "\">" . $label . "</a>'";
        }
        $joined = implode(' + \" \" + ', $parts);
        $formatter = "(_cell, row) => gridjs.html(" . $joined . ")";
        $this->columns[] = ['name' => 'Actions', 'formatter' => $formatter];
        return $this;
    }
}
