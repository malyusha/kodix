<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 07.07.16
 */

namespace Kodix\Database\Relations;

use InvalidArgumentException;
use Kodix\Database\Collection;
use Kodix\Database\Highload\Builder;
use Kodix\Database\Model;

class BelongsToMany extends Relation
{
    protected $foreignKey;
    
    protected $localKey;

    public function __construct(Builder $builder, Model $parent, $foreignKey, $otherKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $otherKey;
        
        parent::__construct($builder, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return mixed
     */
    public function addConstraints()
    {
        if(static::$constraints) {
            $this->builder->where($this->localKey, '=', $this->getParentKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return mixed
     */
    public function addEagerConstraints(array $models)
    {
        return $this->builder->where($this->localKey, '=', $this->getKeys($models, $this->foreignKey));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param $relation
     *
     * @return mixed
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \Kodix\Database\Collection $results
     * @param $relation
     *
     * @return mixed
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $collection = [];
            $keys = $model->getAttribute($this->foreignKey);

            // Нам нужно сохранить порядок сортировки отношения, поэтому мы не можем просто пройти
            // по ключам моделей. Нужно выполнить сравнение всех ключей и ключей конкретной модели
            // и оставить порядок из ключей отношений.
            $diffKeys = array_intersect(array_keys($dictionary), $keys);

            // Здесь хранится множественное свойство, поэтому оно будет массивом из
            // ключей дочерних моделей. Значит мы просто пройдемся по каждому ключу, и если
            // обнаружим в словаре моделей такую запись - то доавбим в коллекцию дочерних моделей.
            foreach ((array)$diffKeys as $key) {
                if(isset($dictionary[$key])) {
                    $collection[] = $dictionary[$key];
                }
            }

            // Затем просто загрузим в модель все отношения, найденые для каждого ключа
            $model->setRelation($relation, $model->newCollection($collection)); 
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->builder->get();
    }

    /**
     * @return mixed
     */
    protected function getParentKey()
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    /**
     * Строит массив, в котором ключами являются значения внешних ключей родительских моделей
     *
     * @param \Kodix\Database\Collection $results
     *
     * @return array
     */
    public function buildDictionary(Collection $results)
    {
        $dictionary = [];
        $local = $this->localKey;

        foreach ($results as $result) {
            $dictionary[$result->{$local}] = $result;
        }

        return $dictionary;
    }

    protected function getKeys($models, $key)
    {
        $keys = [];

        foreach ($models as $model) {
            $keys = array_merge($keys, $model->getAttribute($key));
        }

        return array_unique(array_values($keys));
    }

    /**
     * @param Collection|array $keys
     */
    public function attach($keys)
    {
        return $this->attachOrDetach($keys);
    }

    /**
     * Удаляет из родительской модели привязки к дочерним.
     *
     * @param array|Collection|Model $keys
     * @return bool
     */
    public function detach($keys)
    {
        return $this->attachOrDetach($keys, false);
    }

    /**
     * @param $keys
     * @param bool $attach
     * @return bool
     */
    protected function attachOrDetach($keys, $attach = true)
    {
        $parent = $this->getParent();

        $keys = $this->getArrayableKeys($keys);
        $ids = $parent->getAttribute($this->foreignKey);

        $related = $attach ? array_unique(array_merge($keys, $ids)) : array_diff($ids, $keys);

        $parent->setAttribute($this->foreignKey, $related);

        return $parent->save();
    }

    protected function getArrayableKeys($keys)
    {
        if(is_array($keys)) {
            return $keys;
        }

        if($keys instanceof Model) {
            return [$keys->getKey()];
        }

        if($keys instanceof Collection) {
            return $keys->modelKeys();
        }

        throw new InvalidArgumentException('Keys must be an array of ids or collection of models.');
    }
}