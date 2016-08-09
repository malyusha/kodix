<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 06.07.16
 */

namespace Kodix\Support\Traits\Database;


use Kodix\Support\Str;
use InvalidArgumentException;

trait KeyNormalizer
{
    protected $normalizerPrefix = 'UF_';

    /**
     * Возвращает нормализованный ключ, подставляя к нему префикс и приводя к верхнему регистру
     *
     * @param $key
     * @return string
     */
    public function normalizeKey($key)
    {
        $prefix = property_exists($this, 'prefix') ? $this->prefix : $this->normalizerPrefix;

        if (is_string($key)) {
            dd($this->getPrefixedKey($key));
            return $this->getPrefixedKey($key, $prefix);
        }

        //Значит передан массив ключей и мы вернем массив с измененными ключами
        if (is_array($key)) {
            return array_map(function ($item) use ($prefix) {
                return $this->getPrefixedKey($item, $prefix);
            }, $key);
        }

        throw new InvalidArgumentException('Key must be string or array.');
    }

    protected function getPrefixedKey($key, $prefix)
    {
        //Ключ может начинаться с префикса, а может и без него
        $key = Str::startsWith($key, $prefix) ? $prefix . $key : $key;

        return strtoupper($key);
    }
}