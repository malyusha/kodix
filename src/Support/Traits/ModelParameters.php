<?php


namespace Kodix\Support\Traits;


trait ModelParameters
{
    /**
     * @var array $filter
     */
    protected $filter = [];

    /**
     * @var array $select
     */
    protected $select = [];

    /**
     * @var array $sort
     */
    protected $order = ['ID' => 'ASC'];

    /**
     * Добавляет параметр фильтрации
     * @param string|array $code
     * @param string|null $value
     *
     * @return $this
     */
    public function filter($code, $value = null)
    {
        if(is_array($code)) {
            $this->filter = array_merge($this->filter, $code);
        } else {
            $this->filter[$code] = $value;
        }

        return $this;
    }

    /**
     * Добавляет параметр для выборки
     *
     * @param $field
     *
     * @return $this
     */
    public function select($field)
    {
        if(is_array($field)) {
            $this->select = array_merge($this->select, $field);
        } else {
            $this->select[] = $field;
        }

        return $this;
    }

    /**
     * Устанавливает поле и направление для сортировки
     *
     * @param $field
     * @param $direction
     *
     * @return $this
     */
    public function order($field, $direction)
    {
        if(is_array($field)) {
            $this->sort = $field;
        } else {
            $this->sort[$field] = $direction;
        }

        return $this;
    }
}