<?php

namespace Kodix\File;

use Kodix\Support\Str;

class File
{
    const OPEN_MODE = 'a+';

    protected $filePath;

    protected $file;

    public function __construct($file)
    {
        $this->filePath = $file;
        $this->file = fopen($file, static::OPEN_MODE);
    }

    public function resource()
    {
        return $this->file;
    }

    public function append($text)
    {
        $text = date('d.m.Y H:i:s') . ' ' . $text;

        return fwrite($this->file, $text);
    }

    public function writeLine($text)
    {
        $text = $text . PHP_EOL;

        return $this->append($text);
    }

    public function close()
    {
        fclose($this->file);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param $directory
     * @param $where
     * @param null $root
     *
     * @return bool
     */
    public static function directoryExists($directory, $where, $root = null)
    {
        $root = $root ?: $_SERVER['DOCUMENT_ROOT'];

        $root = Str::endsWith($root, DIRECTORY_SEPARATOR) && !Str::startsWith($where, DIRECTORY_SEPARATOR) ? $root : $root . DIRECTORY_SEPARATOR;

        return in_array($directory, scandir($root . $where));
    }
}