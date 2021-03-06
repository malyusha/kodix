<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 19.07.16
 */

namespace Kodix\FileParser;


use Kodix\Support\Collection;

abstract class CsvParser extends Parser
{
    /**
     * @var array
     */
    protected $extensions = ['csv', 'txt'];

    /**
     * @var int
     */
    protected $fieldsCount = 0;

    /**
     * @var int
     */
    protected $startFrom = 1;

    /**
     * @var string
     */
    protected $delimiter = '|';

    /**
     * @var
     */
    protected $length;

    /**
     * @return Collection
     */
    public function getParsedData()
    {
        $result = [];
        $headers = $this->getHeaders();
        $hasHeader = count($headers) !== 0;

        while(($row = fgetcsv($this->file, $this->length, $this->delimiter)) !== false) {
            // Если установлены заголовки
            if($hasHeader) {
                $this->iterateCounter();

                if($this->isIgnored()) {
                    continue;
                }
            }

            $result[] = $hasHeader ? array_combine($headers, $row) : $row;
        }

        return new Collection($result);
    }

    /**
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * @param $index
     *
     * @return $this
     */
    public function setStartPosition($index)
    {
        $this->startFrom = $index;

        return $this;
    }

    /**
     * @return int
     */
    public function length()
    {
        return $this->fieldsCount;
    }

    /**
     * @return bool
     */
    protected function isIgnored()
    {
        return $this->fieldsCount <= $this->startFrom;
    }

    /**
     *
     */
    protected function iterateCounter()
    {
        $this->fieldsCount++;
    }

    /**
     * Получает заголовки csv файла в виде массива
     *
     * @return array
     */
    abstract public function getHeaders();
}