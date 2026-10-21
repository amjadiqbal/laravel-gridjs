<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

/**
 * Fluent definition of one Grid.js table backed by an Eloquent query. render() prints the
 * container and a mount call; rows come from the package's data endpoint, which receives the
 * query, columns and allowed search/sort fields inside an encrypted token, never as plain
 * request parameters.
 */
class GridBuilder implements Htmlable, Stringable
{
    /** @var list<array{field: ?string, name: string, sortable: ?bool, searchable: ?bool, width: ?string, hidden: bool, formatter: ?string, format: ?array<string, mixed>, actions: ?list<array<string, string>>, key: string|int|null}> */
    protected array $columns = [];

    /** @var bool|list<string> */
    protected bool|array $sortable = false;

    /** @var bool|list<string> */
    protected bool|array $searchable = false;

    protected int $searchDebounce = 300;

    protected ?int $perPage = null;

    protected bool $resizable = false;

    protected bool $fixedHeader = false;

    protected ?string $height = null;

    protected ?string $width = null;

    protected ?string $theme;

    /** @var array<string, string>|null */
    protected ?array $className = null;

    /** @var array<string, mixed>|null */
    protected ?array $language = null;

    /** @var array<string, mixed> */
    protected array $options = [];

    protected ?string $id = null;

    /**
     * @param  Builder<Model>  $query
     */
    public function __construct(protected Builder $query, protected GridManager $manager)
    {
        $this->theme = $manager->defaultTheme();
    }

    /**
     * Strings ('email', 'author.name') or arrays with 'field', 'name', 'sortable', 'searchable',
     * 'width', 'hidden', 'formatter' (a JS function as a string) or 'format' (built-in formats).
     *
     * @param  array<int, string|array<string, mixed>>  $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = array_values(array_filter(
            $this->columns,
            fn (array $c) => $c['actions'] !== null
        ));
        $data = array_map(fn ($c) => $this->normalizeColumn($c), array_values($columns));
        $this->columns = [...$data, ...$this->columns];

        return $this;
    }

    /**
     * true: every plain column (not relation columns or formatter-only columns). A list limits
     * sorting to those fields.
     *
     * @param  bool|list<string>  $columns
     */
    public function sortable(bool|array $columns = true): self
    {
        $this->sortable = $columns;

        return $this;
    }

    /**
     * true: every data column, including relation columns like 'author.name'. A list limits
     * searching to those fields.
     *
     * @param  bool|list<string>  $columns
     */
    public function searchable(bool|array $columns = true, ?int $debounceMs = null): self
    {
        $this->searchable = $columns;
        if ($debounceMs !== null) {
            $this->searchDebounce = $debounceMs;
        }

        return $this;
    }

    public function pagination(?int $limit = null): self
    {
        $this->perPage = max(1, $limit ?? (int) config('gridjs.per_page', 10));

        return $this;
    }

    public function resizable(bool $enabled = true): self
    {
        $this->resizable = $enabled;

        return $this;
    }

    /**
     * A fixed header needs a height for the table body to scroll in.
     */
    public function fixedHeader(bool $enabled = true, string $height = '400px'): self
    {
        $this->fixedHeader = $enabled;
        $this->height ??= $enabled ? $height : null;

        return $this;
    }

    public function height(string $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * 'mermaid' (Grid.js's own theme), null for unstyled, or an array of Grid.js className
     * entries (table, thead, th, td, ...) for Tailwind or your own CSS.
     *
     * @param  string|array<string, string>|null  $theme
     */
    public function theme(string|array|null $theme): self
    {
        if (is_array($theme)) {
            return $this->className($theme);
        }
        $this->theme = $theme === '' ? null : $theme;

        return $this;
    }

    /**
     * @param  array<string, string>  $classes
     */
    public function className(array $classes): self
    {
        $this->className = $classes;
        $this->theme = null;

        return $this;
    }

    /**
     * Grid.js language strings, e.g. ['search' => ['placeholder' => 'Find...']].
     *
     * @param  array<string, mixed>  $language
     */
    public function language(array $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Raw Grid.js options merged into the config (style, autoWidth, ...). Columns, server,
     * search, sort and pagination are managed by the builder and cannot be overridden here.
     *
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Adds an Actions column of links. Each button: 'label', 'url' with an {id} placeholder
     * (or the older 'baseUrl', which appends /{id}), optional 'class' and 'target'. $key is the
     * field whose value fills {id}; it is added as a hidden column when it is not shown.
     *
     * @param  list<array<string, string>>  $buttons
     */
    public function actions(array $buttons, string|int $key = 'id', string $label = 'Actions', ?int $idIndex = null): self
    {
        $normalized = [];
        foreach ($buttons as $button) {
            $url = $button['url'] ?? (rtrim($button['baseUrl'] ?? '#', '/').'/{id}');
            $normalized[] = array_filter([
                'label' => (string) ($button['label'] ?? 'Action'),
                'url' => (string) $url,
                'class' => isset($button['class']) ? (string) $button['class'] : null,
                'target' => isset($button['target']) ? (string) $button['target'] : null,
            ], fn ($v) => $v !== null);
        }

        $this->columns[] = [
            'field' => null, 'name' => $label, 'sortable' => false, 'searchable' => false, 'width' => null,
            'hidden' => false, 'formatter' => null, 'format' => null, 'actions' => $normalized, 'key' => $idIndex ?? $key,
        ];

        return $this;
    }

    public function render(): HtmlString
    {
        $id = $this->id ?? 'gridjs_'.Str::lower(Str::random(10));
        [$columns, $spec, $formatters] = $this->compile();
        $spec['url'] = route('gridjs.data', ['grid' => $this->token($columns)]);

        $json = json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
        $fns = [];
        foreach ($formatters as $index => $source) {
            $fns[] = $index.': ('.$source.')';
        }
        $mount = 'LaravelGridJS.mount('.json_encode($id).', '.$json.', {'.implode(', ', $fns).'})';

        return new HtmlString(
            $this->manager->assetsFor($this->theme)
            .'<div id="'.e($id).'" class="laravel-gridjs"></div>'
            .'<script'.$this->manager->nonceAttribute().'>(function(m){document.readyState==="loading"?document.addEventListener("DOMContentLoaded",m):m();})(function(){'.$mount.';});</script>'
        );
    }

    public function toHtml(): string
    {
        return (string) $this->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * The encrypted payload the data endpoint runs. Public so you can test a grid's data
     * endpoint directly: $this->getJson(route('gridjs.data', ['grid' => $grid->token()])).
     *
     * @param  list<array<string, mixed>>|null  $columns
     */
    public function token(?array $columns = null): string
    {
        $columns ??= $this->compile()[0];

        $query = clone $this->query;
        $base = $query->getQuery();
        $grammar = $base->getGrammar();

        $orders = [];
        foreach ($base->orders ?? [] as $order) {
            if (isset($order['column'])) {
                $column = $order['column'] instanceof Expression ? (string) $order['column']->getValue($grammar) : (string) $order['column'];
                $orders[] = $order['column'] instanceof Expression
                    ? ['sql' => $column.' '.$order['direction']]
                    : ['column' => Str::afterLast($column, '.'), 'direction' => $order['direction']];
            } elseif (isset($order['sql'])) {
                $orders[] = ['sql' => (string) $order['sql']];
            }
        }
        $orderBindings = $base->getConnection()->prepareBindings($base->getRawBindings()['order']);
        $query->reorder();

        $eager = array_keys($query->getEagerLoads());
        $query->setEagerLoads([]);

        $payload = [
            'v' => 1,
            'model' => get_class($query->getModel()),
            'connection' => $query->getModel()->getConnectionName(),
            'sql' => $query->toSql(),
            'bindings' => $base->getConnection()->prepareBindings($query->getBindings()),
            'orders' => $orders,
            'order_bindings' => $orderBindings,
            'with' => $eager,
            'fields' => array_map(fn ($c) => $c['field'], $columns),
            'search' => array_values(array_filter(array_map(fn ($c) => $c['searchable'] ? $c['field'] : null, $columns))),
            'sort' => array_values(array_filter(array_map(fn ($c) => $c['sortable'] ? $c['field'] : null, $columns))),
            'per_page' => $this->perPage,
            'bind' => TokenBinding::forRender(),
            'exp' => now()->addMinutes(max(1, (int) config('gridjs.token_lifetime', 720)))->getTimestamp(),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>, 2: array<int, string>}
     */
    protected function compile(): array
    {
        if ($this->columns === []) {
            throw new InvalidArgumentException('Call ->columns([...]) before rendering a grid.');
        }

        $columns = $this->columns;

        foreach ($columns as $i => $column) {
            if ($column['actions'] === null) {
                continue;
            }
            $key = $column['key'];
            if (is_string($key)) {
                $index = array_search($key, array_column($columns, 'field'), true);
                if ($index === false) {
                    $columns[] = $this->normalizeColumn(['field' => $key, 'hidden' => true, 'sortable' => false, 'searchable' => false]);
                    $index = count($columns) - 1;
                }
                $key = $index;
            }
            $columns[$i]['key'] = (int) $key;
        }

        // A hidden column's value never leaves the server, unless an actions column needs it to fill {id}
        // (that value is in the action links anyway). Hidden columns can't be searched or sorted either:
        // a search box over a hidden column would let anyone probe its values.
        $keys = array_column(array_filter($columns, fn (array $c) => $c['actions'] !== null), 'key');
        foreach ($columns as $i => $c) {
            if (! $c['hidden'] || $c['field'] === null) {
                continue;
            }
            if ($c['searchable'] === true || $c['sortable'] === true) {
                throw new InvalidArgumentException("Column [{$c['field']}] is hidden and cannot be searchable or sortable.");
            }
            $columns[$i]['searchable'] = false;
            $columns[$i]['sortable'] = false;
            if (! in_array($i, $keys, true)) {
                $columns[$i]['field'] = null;
            }
        }

        $compiled = [];
        $spec = ['columns' => []];
        $formatters = [];
        foreach ($columns as $i => $c) {
            $plain = $c['field'] !== null && ! str_contains($c['field'], '.');
            $sortable = $c['field'] !== null && ($c['sortable'] ?? ($plain && $this->listed($this->sortable, $c['field'])));
            if ($sortable && ! $plain) {
                throw new InvalidArgumentException("Column [{$c['field']}] is a relation column and cannot be sorted on the server.");
            }
            if ($c['sortable'] === true && $this->sortable === false) {
                $this->sortable = [];
            }
            $searchable = $c['field'] !== null && ($c['searchable'] ?? $this->listed($this->searchable, $c['field']));
            if ($c['searchable'] === true && $this->searchable === false) {
                $this->searchable = [];
            }

            $compiled[] = ['field' => $c['field'], 'sortable' => $sortable, 'searchable' => $searchable];
            $spec['columns'][] = array_filter([
                'field' => $c['field'],
                'name' => $c['name'],
                'sortable' => $sortable,
                'width' => $c['width'],
                'hidden' => $c['hidden'],
                'format' => $c['format'],
                'actions' => $c['actions'],
                'keyIndex' => $c['actions'] !== null ? $c['key'] : null,
            ], fn ($v) => $v !== null && $v !== false);
            if ($c['formatter'] !== null) {
                $formatters[$i] = $c['formatter'];
            }
        }

        $spec['search'] = $this->searchable !== false ? ['debounce' => $this->searchDebounce] : false;
        $spec['sort'] = $this->sortable !== false;
        $spec['pagination'] = $this->perPage !== null ? ['limit' => $this->perPage] : false;

        $options = array_merge($this->options, array_filter([
            'resizable' => $this->resizable ?: null,
            'fixedHeader' => $this->fixedHeader ?: null,
            'height' => $this->height,
            'width' => $this->width,
            'className' => $this->className,
            'language' => $this->language,
        ], fn ($v) => $v !== null));
        unset($options['columns'], $options['server'], $options['search'], $options['sort'], $options['pagination'], $options['data']);
        $spec['options'] = (object) $options;

        return [$compiled, $spec, $formatters];
    }

    /**
     * @param  bool|list<string>  $setting
     */
    protected function listed(bool|array $setting, string $field): bool
    {
        return is_array($setting) ? in_array($field, $setting, true) : $setting;
    }

    /**
     * @param  string|array<string, mixed>  $column
     * @return array{field: ?string, name: string, sortable: ?bool, searchable: ?bool, width: ?string, hidden: bool, formatter: ?string, format: ?array<string, mixed>, actions: null, key: null}
     */
    protected function normalizeColumn(string|array $column): array
    {
        if (is_string($column)) {
            $column = ['field' => $column];
        }

        $field = $column['field'] ?? null;
        if ($field !== null && ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', (string) $field)) {
            throw new InvalidArgumentException("Invalid grid column field [{$field}].");
        }
        $field = $field === null ? null : (string) $field;
        $name = $column['name'] ?? $column['label'] ?? ($field !== null ? Str::headline(str_replace('.', ' ', $field)) : '');

        return [
            'field' => $field,
            'name' => (string) $name,
            'sortable' => isset($column['sortable']) ? (bool) $column['sortable'] : null,
            'searchable' => isset($column['searchable']) ? (bool) $column['searchable'] : null,
            'width' => isset($column['width']) ? (string) $column['width'] : null,
            'hidden' => (bool) ($column['hidden'] ?? false),
            'formatter' => isset($column['formatter']) ? (string) $column['formatter'] : null,
            'format' => isset($column['format']) && is_array($column['format']) ? $column['format'] : null,
            'actions' => null,
            'key' => null,
        ];
    }
}
