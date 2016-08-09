<?php

namespace Kodix\Database\Relations;

use Kodix\Database\Collection;
use Kodix\Database\Highload\Builder;
use Kodix\Database\Model;

abstract class HasOneOrMany extends Relation
{
    /**
     * @var
     */
    protected $foreignKey;

    /**
     * @var
     */
    protected $localKey;

    /**
     * HasOneOrMany constructor.
     *
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Model $parent
     * @param $foreignKey
     * @param $localKey
     */
    public function __construct(Builder $builder, Model $parent, $foreignKey, $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($builder, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return mixed
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->builder->where($this->foreignKey, '=', $this->getParentKey());
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
        $this->builder->where($this->foreignKey, $this->getKeys($models, $this->localKey));
    }

    /**
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Highload\Builder $parent
     * @param array $columns
     *
     * @return mixed
     */
    public function getRelationQuery(Builder $builder, Builder $parent, $columns = [])
    {
        if ($builder->from == $parent->from) {
            return $this->getRelationQueryForSelfRelation($builder, $parent, $columns);
        }

        return parent::getRelationQuery($builder, $parent, $columns);
    }

    /**
     * @return mixed
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKey();
    }

    /**
     * Возвращает результат запроса для выборки из текущей таблицы
     *
     * @param \Kodix\Database\Highload\Builder $builder
     * @param \Kodix\Database\Highload\Builder $parent
     * @param array $columns
     *
     * @return \Kodix\Database\Highload\Builder
     */
    protected function getRelationQueryForSelfRelation(Builder $builder, Builder $parent, $columns = [])
    {
        $builder->select($columns);

        return $builder->where($this->getForeignKey(), '=', $this->localKey);
    }

    /**
     * Сохраняет дочернюю модель в родительскую и их связь в базе
     *
     * @param \Kodix\Database\Model $model
     *
     * @return bool|\Kodix\Database\Model
     */
    public function save(Model $model)
    {
        $model->setAttribute($this->getForeignKey(), $this->getParentKey());
        
        return $model->save() ? $model : false;
    }

    /**
     * @param array $attributes
     *
     * @return \Kodix\Database\Model
     */
    public function create($attributes = [])
    {
        $instance = $this->related->newInstance($attributes);

        $instance->setAttribute($this->getForeignKey(), $this->getParentKey());

        $instance->save();

        return $instance;
    }

    /**
     * Обвноляет модель отношения
     *
     * @param array $attributes
     *
     * @return bool|int
     */
    public function update($attributes = [])
    {
        if ($this->related->usesTimestamps()) {
            $attributes[$this->relatedUpdatedAt()] = $this->related->freshTimestampString();
        }

        return $this->builder->update($attributes);
    }

    /**
     * Get relation primary key
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * @param array $models
     * @param \Kodix\Database\Collection $results
     * @param $relation
     * @return
     */
    public function matchOne(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    /**
     * @param array $models
     * @param \Kodix\Database\Collection $results
     * @param $relation
     */
    public function matchMany(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    /**
     * @param array $models
     * @param \Kodix\Database\Collection $results
     * @param $relation
     * @param $type
     */
    public function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $value = $this->getRelationValue($dictionary, $key, $type);
                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    /**
     * @param array $dictionary
     * @param $key
     * @param $type
     *
     * @return \Kodix\Database\Collection|mixed
     */
    public function getRelationValue(array $dictionary, $key, $type)
    {
        $value = $dictionary[$key];

        return $type === 'one' ? reset($value) : $this->related->newCollection($value);
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
        $foreign = $this->foreignKey;

        foreach ($results as $result) {
            $dictionary[$result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    /**
     * @return mixed
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}