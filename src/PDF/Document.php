<?php


namespace Kodix\PDF;

use CFile;
use Kodix\File\Exception\FileNotFoundException;
use Kodix\File\Exception\CreateDirectoryException;


abstract class Document extends PDF implements PDFInterface
{

    protected $baseDir = '';

    protected $title = 'Base document';

    protected $created;

    protected $injects = [
        'injectCss',
        'injectImages',
        'injectFonts'
    ];

    protected $additionalInjects = [];

    protected $html;

    protected $template;

    protected $data = [];

    public function __construct($file, $data)
    {
        $this->data = $data;
        $this->baseDir = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->baseDir;
        $this->createBaseDirectoryIfNotExists();

        parent::__construct($file);
    }

    public function send()
    {
        return $this->create()->stream($this->title);
    }

    public function save($fileName)
    {
        $filePath = $this->baseDir . '/' . $fileName . '.pdf';
        $this->created = $filePath;
        if(file_put_contents($this->baseDir . '/' . $fileName . '.pdf', $this->create()->output())) {
            $this->created = $filePath;

            return true;
        }

        return false;
    }

    public function getCreated()
    {
        return CFile::MakeFileArray($this->created);
    }

    public function getCreatedForWeb()
    {
        return 'http://' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '/', $this->created);
    }

    public function hasGenerated($fileName)
    {
        return file_exists($this->baseDir . '/' . $fileName);
    }

    public function create()
    {
        $this->loadHtml($this->getTemplate($this->data));
        $this->setPaper(parent::PAGE_SIZE, parent::PAGE_ORIENTATION);
        $this->render();

        return $this;
    }

    public function show()
    {
        return $this->getTemplate($this->data);
    }

    public function getTemplate($data = [])
    {
        if($this->template != '') {
            return $this->template;
        }

        return $this->template = $this->makeInjects(
            $this->getFileContent($this->file, $data)
        );
    }

    protected function createBaseDirectoryIfNotExists()
    {
        if( is_dir($this->baseDir) ) {
            return;
        }

        if( !mkdir($this->baseDir, 0777, true) ) {
            throw new CreateDirectoryException($this->directory);
        }
    }

    protected function makeInjects($template)
    {
        $htmlTemplate = $template;
        foreach(array_merge($this->injects, $this->additionalInjects) as $method) {
            $htmlTemplate = call_user_func([$this, $method], $htmlTemplate);
        }

        return $htmlTemplate;
    }

    public function injectCss($template)
    {
        return $this->replaceContent($template, '/\<link.+?([\w\d]+\.css).+?\>/', 'style');
    }

    public function injectFonts($template)
    {
        return $this->replaceFont($template, '/\"(.+\.ttf)\"/', 1);
    }

    public function injectImages($template)
    {
        return $this->replacePath($template, '/([\w\d_-]+)\.(png|jpg|jpeg|gif)/');
    }

    public function getFullPath($file)
    {
        return $this->documentsPath . '/' . $file;
    }

    protected function getFileContent($file, $data = [])
    {
        if(!file_exists($file)) {
            throw new FileNotFoundException($file);
        }
        ob_start();
        extract($data, EXTR_OVERWRITE);
        include $file;

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    protected function replaceContent($template, $matchesRegExp, $tag = '')
    {
        $hasMatches = preg_match_all($matchesRegExp, $template, $matches);

        if($hasMatches) {
            foreach($matches[1] as $key => $file) {
                $fileContent = $this->getFileContent($this->getFullPath($file));
                $content = $tag ? "<$tag>\r\n" . $fileContent . "</$tag>" : $fileContent;
                $replace = $this->getQuote($matches[0][$key]);
                $template = preg_replace($replace, $content, $template);
            }
        }

        return $template;
    }

    protected function replacePath($template, $matchesRegExp, $replaceKey = 0)
    {
        $hasMatches = preg_match_all($matchesRegExp, $template, $matches);
        if($hasMatches) {
            foreach($matches[$replaceKey] as $key => $path) {
                $file = $this->assetsPath . DIRECTORY_SEPARATOR . $path;
                $template = preg_replace($this->getQuote($path), $file, $template);
            }
        }

        return $template;
    }

    protected function replaceFont($template, $matchesRegExp, $replaceKey = 0)
    {
        $hasMatches = preg_match_all($matchesRegExp, $template, $matches);
        if($hasMatches) {
            foreach($matches[$replaceKey] as $key => $path) {
                $file = $this->webPath . DIRECTORY_SEPARATOR . $path;
                $template = preg_replace($this->getQuote($path), $file, $template);
            }
        }

        return $template;
    }

    protected function getQuote($string)
    {
        return '/' . preg_quote($string, '/') . '/';
    }

}