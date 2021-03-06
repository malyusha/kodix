<?php

namespace Kodix\Database\Relations;

use Kodix\Database\Collection;

class HasOne extends HasOneOrMany
{

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
     *
     * @return mixed
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->builder->first();
    }
}