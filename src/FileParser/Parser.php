<?php


namespace Kodix\FileParser;


use Kodix\File\Exception\FileNotFoundException;
use Kodix\File\Exception\IncorrectFileExtension;
use Kodix\FileParser\Exception\DataNotParsedException;

abstract class Parser
{
    const FILE_READ_MODE = 'r';

    protected $extensions = [
        'csv',
        'xls',
        'xlsx',
        'doc',
        'docx',
        'txt'
    ];

    protected $file;

    protected $filePath;

    protected $parsedData;

    public function __construct($file)
    {
        $this->checkFileExists($file);
        $this->checkFileHasCorrectExtension($file);

        $this->filePath = $file;
    }

    /**
     * @param $file
     *
     * @return $this
     */
    public function parse()
    {
        $this->file = fopen($this->filePath, static::FILE_READ_MODE);

        $this->parsedData = $this->getParsedData();
        
        fclose($this->file);

        return $this;
    }

    /**
     * @return array
     */
    public function get()
    {
        if(is_null($this->parsedData)) {
            throw new DataNotParsedException;
        }

        return $this->parsedData;
    }

    private function checkFileExists($file)
    {
        if(!file_exists($file)) {
            throw new FileNotFoundException($file);
        }
    }

    private function checkFileHasCorrectExtension($file)
    {
        $info = pathinfo($file);

        if(!preg_match('/(' . implode('|', $this->extensions) . ')/', $info['extension'])) {
            throw new IncorrectFileExtension($file, $this->extensions);
        }
    }

    abstract public function getParsedData();
}