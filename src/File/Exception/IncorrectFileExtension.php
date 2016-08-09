<?php

namespace Kodix\File\Exception;

class IncorrectFileExtension extends FileException
{
    public function __construct($file, array $allowed)
    {
        parent::__construct(sprintf('File %s has incorrect extension. Allowed extensions: %s', $file, implode(',', $allowed)));
    }
}