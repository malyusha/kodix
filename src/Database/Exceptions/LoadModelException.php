<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 16.06.16
 */

namespace Kodix\Database\Exceptions;


use Exception;
use Kodix\Models\Model;
use ReflectionClass;

class LoadModelException extends ModelException
{
    public function __construct($class)
    {
        parent::__construct(sprintf('Class %s must be instance of Kodix\Models\Model.', $this->getBaseName($class)));
    }

    protected function getBaseName($class)
    {
        return (new ReflectionClass($class))->getName();
    }
}