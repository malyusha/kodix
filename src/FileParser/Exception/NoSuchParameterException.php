<?php


namespace Kodix\FileParser\Exception;


class NoSuchParameterException extends ParameterException
{
    public function __construct($parameter)
    {
        parent::__construct(sprintf('No such parameter %s in class %s', $parameter, $this->getCaller()));
    }

    protected function getCaller()
    {
        $trace = debug_backtrace();

        return $trace['class'];
    }
}