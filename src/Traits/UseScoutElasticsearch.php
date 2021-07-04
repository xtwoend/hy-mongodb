<?php

namespace Xtwoend\HyMongo\Traits;

/**
 * 
 */
trait UseScoutElasticsearch
{
    public function toSearchableArray()
    {
        $array = $this->toArray();
        unset($array['_id']);

        return $array;
    }
}
