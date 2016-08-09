<?php

namespace Kodix\Support\Traits;

use Kodix\Support\Arr;
use Kodix\Support\Str;

trait ManipulateAttributes
{
    /**
     * Приводит к верхнему регистру значение
     *
     * @param $value
     *
     * @return array|string
     */
    public static function toUpper($value)
    {
        if (is_array($value)) {
            if (Arr::isAssoc($value)) {
                return static::assocKeysToUpper($value);
            }

            return static::arrayToUpper($value);
        }

        return Str::upper($value);
    }

    /**
     * Приводт к верхнему регистру ключи ассоциативного массива
     *
     * @param $attributes
     *
     * @return array
     */
    protected static function assocKeysToUpper($attributes)
    {
        $keys = static::arrayToUpper(array_keys($attributes));

        return array_combine($keys, $attributes);
    }

    /**
     * Приводит значения массива к верхнему регистру
     *
     * @param $attributes
     *
     * @return array
     */
    protected static function arrayToUpper($attributes)
    {
        return array_map(function ($attribute) {
            return static::toUpper($attribute);
        }, $attributes);
    }
}