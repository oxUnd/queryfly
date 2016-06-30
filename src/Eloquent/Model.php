<?php namespace Epsilon\Queryfly\Eloquent;


use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Epsilon\Queryfly\Query\Builder as QueryBuilder;
use ReflectionMethod;

class Model extends BaseModel {

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        return new QueryBuilder($conn, $conn->getPostProcessor());
    }
}
