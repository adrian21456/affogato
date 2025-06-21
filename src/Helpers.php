<?php

namespace Zchted\Affogato;

use Doctrine\Inflector\InflectorFactory;

abstract class Helpers
{

    public static function pluralize($word): string
    {
        $inflector = InflectorFactory::create()->build();
        $word = explode(' ', $word);
        $last_word = end($word);
        return $inflector->pluralize($last_word);
    }

    public static function properName($name)
    {
        $name = str_replace('_', ' ', $name);
        return ucwords($name);
    }
}
