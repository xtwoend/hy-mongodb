<?php

namespace Xtwoend\HyMongo\Relations;

use Hyperf\Database\Model\Model as EloquentModel;
use Hyperf\Database\Model\Relations\MorphMany as EloquentMorphMany;

class MorphMany extends EloquentMorphMany
{
    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param \Hyperf\Database\Model\Model $model
     * @param string $key
     *
     * @return string
     */
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }
}
