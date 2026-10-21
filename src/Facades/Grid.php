<?php

namespace AmjadIqbal\GridJS\Facades;

use AmjadIqbal\GridJS\GridBuilder;
use AmjadIqbal\GridJS\GridManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;

/**
 * @method static GridBuilder fromQuery(Builder<\Illuminate\Database\Eloquent\Model> $query)
 * @method static HtmlString assets(string|false|null $theme = null)
 *
 * @see GridManager
 */
class Grid extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gridjs';
    }
}
