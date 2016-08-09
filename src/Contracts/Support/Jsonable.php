<?php

namespace Kodix\Contracts\Support;

interface Jsonable
{
    /**
     * Конвертирует объект в его json представление.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0);
}
