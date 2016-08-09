<?php


namespace Kodix\XML;

use DOMDocument;
use DOMElement;

abstract class Xml
{
    protected $version = '1.0';

    protected $encoding = 'UTF-8';

    /**
     * @var DomDocument
     */
    protected $xml;

    public function __construct()
    {
        $this->createDocument();
    }

    /**
     * Set the version of document
     *
     * @param mixed $version
     * @return Xml
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Set encoding of document
     *
     * @param string $encoding
     * @return Xml
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;

        return $this;
    }

    protected function createDocument()
    {
        return $this->xml = new DOMDocument($this->version, $this->encoding);
    }

    /**
     * @param DomElement $parent
     * @param $data
     *
     * @return DomElement
     */
    public function addData($parent, $data)
    {
        foreach($data as $code => $value) {
            if( is_array($value) ) {
                $code = $value['code'] ?: $code;
                $value = $value['data'] ?: $value;
                $newParent = $this->xml->createElement($code);
                $parent->appendChild($this->addData($newParent, $value));
            } else {
                $element = $this->xml->createElement($code, trim($value));
                $parent->appendChild($element);
            }
        }

        return $parent;
    }
}