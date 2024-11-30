<?php

namespace Santosdave\VerteilWrapper\Facades;

use Illuminate\Support\Facades\Facade;

class Verteil extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'verteil';
    }
}


