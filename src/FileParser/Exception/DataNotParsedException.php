<?php


namespace Kodix\FileParser\Exception;



class DataNotParsedException extends DataException
{
    public function __construct()
    {
        parent::__construct('No parsed data presented.');
    }
}