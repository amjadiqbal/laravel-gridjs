<?php

namespace AmjadIqbal\GridJS\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'teams';

    protected $guarded = [];

    public $timestamps = false;
}
