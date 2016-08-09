<?php

namespace Kodix\File\Exception;

class FileNotFoundException extends FileException
{
    public function __construct($file)
    {
        parent::__construct(sprintf('File %s not found', $file));
    }
}