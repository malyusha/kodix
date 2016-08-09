<?php

namespace Kodix\Support;

use Carbon\Carbon as BaseCarbon;
use DateTime;

class Carbon extends BaseCarbon
{
    const DEFAULT_TO_STRING_FORMAT = 'd.m.Y H:i:s';

    /**
     * Create a Carbon instance from a DateTime one
     *
     * @param DateTime $dt
     *
     * @return static
     */
    public static function instance(DateTime $dt)
    {
        return new static($dt->format(static::DEFAULT_TO_STRING_FORMAT), $dt->getTimezone());
    }

    /**
     * Format the instance as date and time
     *
     * @return string
     */
    public function toDateTimeString()
    {
        return $this->format(static::DEFAULT_TO_STRING_FORMAT);
    }
}