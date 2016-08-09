<?php


namespace Kodix\Support\Traits\Component;


trait ComponentProps
{
    /**
     * Обработчики свойств разных типов
     *
     * @var array
     */
    protected $resolvers = ['resolveEnum'];

    /**
     * Необходимость очищать пустые свойства
     *
     * @var bool $needRemoveEmpty
     */
    protected $needRemoveEmpty;

    /**
     * Обрабатывает свойства элемента, пропуская их через обработчики
     *
     * @param array $element
     * @return array
     */
    public function getProperties($element)
    {
        $result = [];
        foreach($this->arParams['PROPS_LIST'] as $property) {
            if( in_array($property, $this->ignore) ) {
                continue;
            }

            foreach($this->resolvers as $method) {
                $result[$property] = call_user_func([$this, $method], $element, $property);
            }
        }

        foreach($this->additional as $key => $callback) {
            $result[$key] = call_user_func($callback, $element);
        }

        $result = $this->needRemoveEmpty ? $this->removeEmpty($result) : $result;

        return $this->removeEmpty($result);
    }

    /**
     * Удаляет пустые свойства элемента
     *
     * @param array $properties
     * @return array
     */
    public function removeEmpty($properties)
    {
        $result = [];
        foreach($properties as $code => $prop) {
            if( !$prop )
                continue;

            $result[$code] = $prop;
        }

        return $result;
    }

    /**
     * Получает enum коды у свойств, перечисленных в массиве enums
     *
     * @param $element
     * @param $property
     * @return array
     */
    public function resolveEnum($element, $property)
    {
        if( in_array($property, $this->enums) ) {
            $result = [];
            $props = \CIBlockPropertyEnum::GetList([], [
                $this->arParams['IBLOCK_ID'],
                'ID' => $element['PROPERTY_' . $property . '_ENUM_ID']
            ]);

            while($prop = $props->Fetch()) {
                $result[] = $prop['XML_ID'];
            }

            return $result;
        }

        return $this->resolveMain($element, $property);
    }

    /**
     * Возвращает свойство элемента
     *
     * @param $element
     * @param $property
     * @return mixed
     */
    public function resolveMain($element, $property)
    {
        return $element['PROPERTY_' . $property . '_VALUE'];
    }

    /**
     * Добавляет обработчик свойств в массив обработчиков
     *
     * @param $resolver
     * @return bool
     */
    public function addResolver($resolver)
    {
        if(!in_array($resolver, $this->resolvers)) {
            $this->resolvers[] = $resolver;
        }

        return true;
    }
}