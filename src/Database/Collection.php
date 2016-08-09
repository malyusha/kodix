<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 06.07.16
 */

namespace Kodix\Database;

use Kodix\Support\Arr;
use Kodix\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * @param mixed $key
     * @param null $value
     *
     * @return bool
     */
    public function contains($key, $value = null)
    {
        if(func_num_args() == 2) {
            return parent::contains($key, $value);
        }
        
        if($this->useAsCallable($key)) {
            return parent::contains($key);
        }
        
        $key = $key instanceof Model ? $key->getKey() : $key;

        return parent::contains($key);
    }

    public function unique($key = null)
    {
        if(!is_null($key)) {
            return parent::unique($key);
        }

        return new static(array_values($this->getDictionary()));
    }

    /**
     * ..
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function load($relations)
    {
        if (count($this->items) > 0) {
            if (is_string($relations)) {
                $relations = func_get_args();
            }

            $builder = $this->first()->newBuilder()->with($relations);

            $this->items = $builder->eagerLoadRelations($this->items);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function modelKeys()
    {
        return array_map(function ($model) {
            return $model->getKey();
        }, $this->items);
    }

    /**
     * @param null $items
     *
     * @return array
     */
    public function getDictionary($items = null)
    {
        $items = is_null($items) ? $this->items : $items;
        
        $dictionary = [];
        foreach($items as $item) {
            $dictionary[$item->getKey()] = $item;
        }
        
        return $dictionary;
    }

    /**
     * Merge the collection with the given items.
     *
     * @param  \ArrayAccess|array  $items
     * @return static
     */
    public function merge($items)
    {
        $dictionary = $this->getDictionary();

        foreach ($items as $item) {
            $dictionary[$item->getKey()] = $item;
        }

        return new static(array_values($dictionary));
    }

    /**
     * Add an item to the collection.
     *
     * @param  mixed  $item
     * @return $this
     */
    public function add($item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     *
     *
     * @param mixed $items
     *
     * @return static
     */
    public function diff($items)
    {
        $diff = new static;

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (! isset($dictionary[$item->getKey()])) {
                $diff->add($item);
            }
        }

        return $diff;
    }

    /**
     * @param mixed $key
     * @param null $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return parent::get($key, $default);
    }

    /**
     * @return \Kodix\Support\Collection
     */
    public function toBase()
    {
        return new BaseCollection($this->items);
    }
}