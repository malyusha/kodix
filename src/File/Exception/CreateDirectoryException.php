<?php

namespace Kodix\File\Exception;

class CreateDirectoryException extends FileException
{
    public function __construct($directory)
    {
        parent::__construct(sprintf('Failed to create directory %s', $directory));
    }
}