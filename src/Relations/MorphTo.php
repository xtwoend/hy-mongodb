<?php

namespace Xtwoend\HyMongo\Relations;

use Hyperf\DbConnection\Traits\HasContainer;
use Hyperf\Database\Model\Relations\Constraint;
use Hyperf\Database\Model\Model as EloquentModel;
use Hyperf\Database\Model\Relations\MorphTo as EloquentMorphTo;

class MorphTo extends EloquentMorphTo
{ 
    /**
     * @inheritdoc
     */
    public function addConstraints()
    {
        if (Constraint::isConstraint()) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->getOwnerKey(), '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * @inheritdoc
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $key = $instance->getKeyName();

        $query = $instance->newQuery();

        return $query->whereIn($key, $this->gatherKeysByType($type, $instance->getKeyType()))->get();
    }

    /**
     * Get the owner key with backwards compatible support.
     * @return string
     */
    public function getOwnerKey()
    {
        return property_exists($this, 'ownerKey') ? $this->ownerKey : $this->otherKey;
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
