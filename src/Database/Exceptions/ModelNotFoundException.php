<?php

namespace Kodix\Database\Exceptions;

class ModelNotFoundException extends ModelException
{
    protected $model;
    
    public function setModel($model)
    {
        $this->model = $model;
        
        $this->message = "No query results for model [{$model}]";
        
        return $this;
    }
    
    public function getModel()
    {
        return $this->model;
    }
}