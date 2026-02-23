<?php

namespace AmjadIqbal\GridJS\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Eloquent\Builder;
use AmjadIqbal\GridJS\GridBuilder;
use Illuminate\Support\Facades\App;

class Grid extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'gridjs';
    }

    public static function fromQuery(Builder $query): GridBuilder
    {
        return App::make('gridjs')->fromQuery($query);
    }
}
