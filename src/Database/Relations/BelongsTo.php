<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 07.07.16
 */

namespace Kodix\Database\Relations;


use Kodix\Database\Collection;
use Kodix\Database\Highload\Builder;
use Kodix\Database\Model;

class BelongsTo extends Relation
{
    /**
     * Внешний ключ ведущий на родительскую модель
     * 
     * @var string
     */
    protected $foreignKey;

    /**
     * Ключ родительской модели
     * 
     * @var string
     */
    protected $otherKey;

    /**
     * Название отношения.
     * 
     * @var string
     */
    protected $relation;

    /**
     * Создает экземпляр отношения "belongs to".
     * 
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Model $parent
     * @param $foreignKey
     * @param $otherKey
     * @param $relation
     */
    public function __construct(Builder $builder, Model $parent, $foreignKey, $otherKey, $relation)
    {
        $this->foreignKey = $foreignKey;
        $this->otherKey = $otherKey;
        $this->relation = $relation;
        
        parent::__construct($builder, $parent);
    }

    /**
     * @return mixed
     */
    public function getResults()
    {
        return $this->builder->first();
    }

    /**
     * @return mixed
     */
    public function addConstraints()
    {
        if(static::$constraints) {
            $this->builder->where($this->otherKey, '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * @param array $models
     * @return mixed
     */
    public function addEagerConstraints(array $models)
    {
        $this->builder->where($this->otherKey, '=', $this->getEagerModelKeys($models));
    }

    /**
     * @param array $models
     * @param $relation
     * @return mixed
     */
    public function initRelation(array $models, $relation)
    {
       foreach ($models as $model) {
           $model->setRelation($relation, null);
       }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \Kodix\Database\Collection $results
     * @param $relation
     * @return mixed
     */
    public function match(array $models, Collection $results, $relation)
    {
        $foreign = $this->foreignKey;

        $other = $this->otherKey;

        // Сначала мы получим список дочерних моделей по их первичному ключу в отношении
        // затем мы можем легко можем узнать какая дочерняя модель принадлежит какой
        // родительской и присоединить все дочерние модели по родительскому ключу.
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($other)] = $result;
        }

        // Когда мы создали список мы можем пройти по всем родительским моделям
        // и найти дочерние элементы, используя внешние ключи родительской
        // модели и сопоставляя их с ключом созданного списка дочерних.
        foreach ($models as $model) {
            if(isset($dictionary[$model->$foreign])) {
                $model->setRelation($relation, $dictionary[$model->$foreign]);
            }
        }

        return $models;
    }

    /**
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Highload\Builder $parent
     * @param array $columns
     * @return mixed
     */
    public function getRelationQuery(Builder $builder, Builder $parent, $columns = [])
    {
        $builder->select($columns);

        return $builder->where($this->foreignKey, '=', $this->otherKey);
    }

    /**
     * Получает все ключи переданных моделей отдельным массивом.
     *
     * @param array $models
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = [];

        foreach ($models as $model) {
            if(!is_null($value = $model->{$this->foreignKey})) {
                $keys[] = $value;
            }
        }

        if(count($keys) === 0) {
            return [];
        }

        return array_values(array_unique($keys));
    }

    /**
     * Устанавливает переданную модель как дочернюю для родительской.
     *
     * @param \Kodix\Database\Model|int $model
     * @return \Kodix\Database\Model
     */
    public function associate($model)
    {
        $isModelInstance = ($model instanceof Model);
        $otherKey = ($isModelInstance ? $model->getAttribute($this->otherKey) : $model);

        $this->parent->setAttribute($this->foreignKey, $otherKey);

        if($isModelInstance) {
            $this->parent->setRelation($this->relation, $model);
        }

        return $this->parent;
    }

    /**
     * Убирает модель из дочерних у родительской модели.
     *
     * @return \Kodix\Database\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent->setRelation($this->relation, null);
    }

    /**
     * Обновляет родительскую модель
     *
     * @param array $attributes
     * @return mixed
     */
    public function update(array $attributes)
    {
        $instance = $this->getResults();

        return $instance->fill($attributes)->save();
    }

    /**
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * @return string
     */
    public function getOtherKey()
    {
        return $this->otherKey;
    }

}