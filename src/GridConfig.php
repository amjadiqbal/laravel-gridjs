<?php

namespace AmjadIqbal\GridJS;

class GridConfig
{
    public function __construct(
        protected array $columns = [],
        protected bool $sortable = false,
        protected bool $searchable = false,
        protected ?int $paginationLimit = null,
        protected bool $resizable = false,
        protected bool $fixedHeader = false,
        protected ?array $className = null
    ) {
    }

    public function toArray(): array
    {
        $config = [
            'columns' => $this->columns,
            'sort' => $this->sortable,
            'search' => $this->searchable,
            'resizable' => $this->resizable,
            'fixedHeader' => $this->fixedHeader,
        ];

        if ($this->paginationLimit !== null) {
            $config['pagination'] = [
                'enabled' => true,
                'limit' => $this->paginationLimit,
            ];
        }

        if ($this->className) {
            $config['className'] = $this->className;
        }

        return $config;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }
}
