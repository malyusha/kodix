<?php


namespace Kodix\PDF;


interface PDFInterface
{
    public function send();

    public function save($file);
}