<?php


namespace Kodix\Log;


use Kodix\File\File;

class Logger implements LoggerInterface
{
    protected $baseDir = '';

    protected $file;

    protected $fileName = 'site.log';

    public function __construct($file = null)
    {
        $this->fileName = is_null($file) ? $this->fileName : $file;
        $this->file = new File($this->baseDir . '/' . $this->fileName);
    }

    public function log($text)
    {
        return $this->file->writeLine($text);
    }

    public function error($text)
    {
        $text = $this->setPrefix('Error', $text);

        return $this->log($text);
    }

    public function info($text)
    {
        $text = $this->setPrefix('Info', $text);

        return $this->log($text);
    }

    protected function setPrefix($prefix, $text)
    {
        return $prefix . ': ' . $text;
    }
}