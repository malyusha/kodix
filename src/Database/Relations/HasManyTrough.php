<?php

namespace Kodix\Database\Relations;

use Kodix\Database\Collection;
use Kodix\Database\Highload\Builder;
use Kodix\Database\Model;

class HasManyThrough extends Relation
{
    /**
     * The distance parent model instance.
     *
     * @var \Kodix\Database\Model
     */
    protected $farParent;

    /**
     * The near key on the relationship.
     *
     * @var string
     */
    protected $firstKey;

    /**
     * The far key on the relationship.
     *
     * @var string
     */
    protected $secondKey;

    /**
     * The local key on the relationship.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Create a new has many through relationship instance.
     *
     * @param  \Kodix\Database\Highload\Builder  $builder
     * @param  \Kodix\Database\Model  $farParent
     * @param  \Kodix\Database\Model  $parent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $builder, Model $farParent, Model $parent, $firstKey, $secondKey, $localKey)
    {
        $this->localKey = $localKey;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->farParent = $farParent;

        parent::__construct($builder, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        $localValue = $this->farParent[$this->localKey];


        if (static::$constraints) {
            $this->builder->where($this->firstKey, '=', $localValue);
        }
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Kodix\Database\Highload\Builder  $builder
     * @param  \Kodix\Database\Highload\Builder  $parent
     * @param  array|mixed  $columns
     * @return \Kodix\Database\Highload\Builder
     */
    public function getRelationQuery(Builder $builder, Builder $parent, $columns = ['*'])
    {
        $builder->select($columns);

        return $builder->where($this->getHasCompareKey(), '=', $this->firstKey);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->builder->where($this->firstKey, $this->getKeys($models));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
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
     * @param  array   $models
     * @param  \Kodix\Database\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getKey();

            if (isset($dictionary[$key])) {
                $value = $this->related->newCollection($dictionary[$key]);

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Kodix\Database\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        $foreign = $this->firstKey;

        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $result) {
            $dictionary[$result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Execute the query and get the first related model.
     *
     * @param  array   $columns
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? $results->first() : null;
    }
    
    /**
     * Find a related model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Kodix\Database\Model|\Kodix\Database\Collection|null
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        $this->where($this->getRelated()->getKeyName(), '=', $id);

        return $this->first($columns);
    }

    /**
     * Find multiple related models by their primary keys.
     *
     * @param  mixed  $ids
     * @param  array  $columns
     * @return \Kodix\Database\Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        if (empty($ids)) {
            return $this->getRelated()->newCollection();
        }

        $this->where($this->getRelated()->getKeyName(), $ids);

        return $this->get($columns);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Kodix\Database\Collection
     */
    public function get($columns = [])
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $columns = $this->builder->select ? [] : $columns;

        $select = $columns;
        
        $builder = clone $this->builder;

        $models = $builder->addSelect($select)->getModels();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->farParent->getKeyName();
    }

    /**
     * Get the qualified foreign key on the related model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->secondKey;
    }

    /**
     * Get the qualified foreign key on the "through" model.
     *
     * @return string
     */
    public function getThroughKey()
    {
        return $this->firstKey;
    }
}
