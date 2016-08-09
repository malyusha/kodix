<?php

namespace Kodix\Importer;

use Kodix\FileParser\Parser;

/**
 * Class Importer
 *
 * @package Kodix\Importer
 */
abstract class Importer
{
    /**
     * @var \Kodix\FileParser\Parser
     */
    protected $parser;

    /**
     * Importer constructor.
     *
     * @param \Kodix\FileParser\Parser $parser
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
        $this->parser->parse();
    }

    /**
     * @return \Kodix\Support\Collection
     */
    public function getData()
    {
        return $this->parser->get();
    }

    /**
     * Сохраняет результаты импорта в базу
     *
     * @return mixed
     */
    abstract public function save();
}