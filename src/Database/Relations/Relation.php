<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 07.07.16
 */

namespace Kodix\Database\Relations;


use Closure;
use Exception;
use Kodix\Database\Collection;
use Kodix\Database\Highload\Builder;
use Kodix\Database\Model;

/**
 * Class Relation
 * @package Kodix\Database\Relations
 */
abstract class Relation
{
    /**
     * @var \Kodix\Database\Highload\Builder
     */
    protected $builder;

    /**
     * @var \Kodix\Database\Model
     */
    protected $parent;

    /**
     * @var \Kodix\Database\Model
     */
    protected $related;

    /**
     * Флаг добавления ограничений отношений.
     *
     * @var bool
     */
    protected static $constraints = true;

    /**
     * Relation constructor.
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Model $parent
     */
    public function __construct(Builder $builder, Model $parent)
    {
        $this->builder = $builder;
        $this->parent = $parent;
        $this->related = $builder->getModel();

        $this->addConstraints();
    }

    /**
     * @return mixed
     */
    public function getEager()
    {
        return $this->get();
    }

    /**
     * @param \Closure $callback
     * @return mixed
     */
    public static function noConstraints(Closure $callback)
    {
        $previous = static::$constraints;

        static::$constraints = false;

        try {
            $results = call_user_func($callback);
        } catch (Exception $e) {
            static::$constraints = $previous;
        }

        static::$constraints = $previous;

        return $results;
    }

    /**
     *
     */
    public function touch()
    {
        $column = $this->getRelated()->getUpdatedAtColumn();

        $this->rawUpdate([$column => $this->getRelated()->freshTimestampString()]);
    }

    /**
     * @param array $attributes
     * @return bool|int
     */
    public function rawUpdate(array $attributes = [])
    {
        return $this->builder->update($attributes);
    }

    /**
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Highload\Builder $parent
     * @param array $columns
     */
    public function getRelationQuery(Builder $builder, Builder $parent, $columns = [])
    {
        $builder->select($columns);

        $key = $this->getParentKeyName();

        return $builder->where($this->getHasCompareKey(), '@', $parent->getModel()->getAttribute($key));

    }

    /**
     * @return string
     */
    public function getParentKeyName()
    {
        return $this->parent->getKeyName();
    }

    /**
     * Получает все первичные ключи для переданного массива моделей
     *
     * @param array $models
     * @param null $key
     * @return array
     */
    protected function getKeys(array $models, $key = null)
    {
        return array_unique(array_values(array_map(function ($value) use ($key) {
            return $key ? $value->getAttribute($key) : $value->getKey();
        }, $models)));
    }

    /**
     * @return \Kodix\Database\Highload\Builder
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * @return \Kodix\Database\Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return \Kodix\Database\Model
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * @return string
     */
    public function createdAt()
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * @return string
     */
    public function updatedAt()
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * @return string
     */
    public function relatedUpdatedAt()
    {
        return $this->related->getUpdatedAtColumn();
    }

    /**
     * @param $method
     * @param $parameters
     * @return $this|mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array([$this->builder, $method], $parameters);

        if ($result === $this->builder) {
            return $this;
        }

        return $result;
    }

    /**
     *
     */
    public function __clone()
    {
        $this->builder = clone $this->builder;
    }

    /**
     * Set the base constraints on the relation query.
     * 
     * @return mixed
     */
    abstract public function addConstraints();

    /**
     * Set the constraints for an eager load of the relation.
     * 
     * @param array $models
     * @return mixed
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Initialize the relation on a set of models.
     * 
     * @param array $models
     * @param $relation
     * @return mixed
     */
    abstract public function initRelation(array $models, $relation);

    /**
     * Match the eagerly loaded results to their parents.
     * 
     * @param array $models
     * @param \Kodix\Database\Collection $results
     * @param $relation
     * @return mixed
     */
    abstract public function match(array $models, Collection $results, $relation);

    /**
     * Get the results of the relationship.
     * 
     * @return mixed
     */
    abstract public function getResults();

}