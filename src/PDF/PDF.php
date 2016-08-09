<?php


namespace Kodix\PDF;

use Dompdf\Dompdf;
use Kodix\File\Exception\FileNotFoundException;

abstract class PDF extends Dompdf
{
    const PAGE_SIZE = 'A4';

    const PAGE_ORIENTATION = 'portrait';

    protected $documentsPath;

    protected $webPath;

    protected $file;

    protected $assetsPath;

    public function __construct($htmlFile)
    {
        $this->setWebPath($this->getFilePath($htmlFile));
        $this->setAssetsPath($this->getFilePath($htmlFile));
        $this->setDocumentsPath($this->getFilePath($htmlFile));
        $this->setFile($htmlFile);

        if(!file_exists($this->file)) {
            throw new FileNotFoundException($this->file);
        }

        parent::__construct();

        $this->_paper_size = self::PAGE_SIZE;
    }

    private function getFilePath($file, $onlyDirectory = true)
    {
        $paths = explode(DIRECTORY_SEPARATOR, $file);
        if($onlyDirectory) {
            unset($paths[array_search(end($paths), $paths)]);

            return implode(DIRECTORY_SEPARATOR, $paths);
        }

        return end($paths);
    }

    private function setFile($file)
    {
        $file = $this->getFilePath($file, false);
        $this->file = $this->documentsPath . DIRECTORY_SEPARATOR . $file;
    }

    private function setDocumentsPath($path)
    {
        $this->documentsPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $path;
    }

    private function setWebPath($path)
    {
        $this->webPath = 'http://' . $_SERVER['SERVER_NAME'] . DIRECTORY_SEPARATOR . str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->documentsPath) . $path;
    }

    private function setAssetsPath($path)
    {
        $this->assetsPath = DIRECTORY_SEPARATOR . str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->documentsPath) . $path;
    }
}