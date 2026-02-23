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
        if (!static::$assetsInjected) {
            if ($this->theme === 'mermaid') {
                $cssLinks .= '<link rel="stylesheet" href="https://unpkg.com/gridjs/dist/theme/mermaid.min.css">';
            } elseif ($this->theme === 'skeleton') {
                $cssLinks .= '<link rel="stylesheet" href="https://unpkg.com/gridjs/dist/theme/skeleton.min.css">';
            }
        }

        $configJson = $config->toJson();
        $columnsJs = $this->buildColumnsJs();
        $scriptTag = '';
        if (!static::$assetsInjected) {
            $scriptTag = '<script src="https://unpkg.com/gridjs/dist/gridjs.umd.js"></script>';
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
            if (is_string($formatter)) {
                $items[] = "{name: '{$name}', formatter: {$formatter}}";
            } else {
                $items[] = "{name: '{$name}'}";
            }
        }
        $arrayJs = '[' . implode(',', $items) . ']';
        return "baseConfig.columns = {$arrayJs};";
    }
}
