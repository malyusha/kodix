<?php


namespace Kodix\XML;


interface XmlInterface
{
    /**
     * Saves generated file to the system
     * Must return generated file path
     *
     * @return string
     */
    public function save();
}