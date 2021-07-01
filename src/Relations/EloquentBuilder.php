<?php

namespace Xtwoend\HyMongo\Relations;

use Hyperf\Database\Model\Builder;
use Xtwoend\HyMongo\Traits\QueriesRelationships;

class EloquentBuilder extends Builder
{
    use QueriesRelationships;
}
