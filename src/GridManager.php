<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Database\Eloquent\Builder;

class GridManager
{
    public function fromQuery(Builder $query): GridBuilder
    {
        return new GridBuilder($query);
    }
}
