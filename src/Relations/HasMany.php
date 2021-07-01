<?php

namespace Xtwoend\HyMongo\Relations;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model as EloquentModel;
use Hyperf\Database\Model\Relations\HasMany as EloquentHasMany;

class HasMany extends EloquentHasMany
{
    /**
     * Get the plain foreign key.
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getHasCompareKey();

        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Get the name of the "where in" method for eager loading.
     * @param \Hyperf\Database\Model\Model $model
     * @param string $key
     * @return string
     */
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }
}
