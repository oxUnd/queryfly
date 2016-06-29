<?php namespace Epsilon\Queryfly\Query;

use Closure;
use DateTime;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Epsilon\Queryfly\Connection;

class Builder extends BaseBuilder
{


    protected $selectComponents = array(
        'aggregate',
        'columns',
        // 'from',
        'joins',
        'wheres',
        // 'groups',
        // 'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    );

    /**
     * The cursor timeout value.
     *
     * @var int
     */
    public $timeout;

    /**
     * The cursor hint value.
     *
     * @var int
     */
    public $hint;

    /**
     * Indicate if we are executing a pagination query.
     *
     * @var bool
     */
    public $paginating = false;

    /**
     * Operator conversion.
     *
     * @var array
     */
    protected $conversion = [
        '='  => 'eq',
        '!=' => '!eq',
        '<>' => 'eq',
        '<'  => 'lt',
        '<=' => 'lte',
        '>'  => 'gt',
        '>=' => 'gte',
        'not like' => '!like'
    ];


    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between'
        // , 'like binary'
        // , 'ilike',
        // '&', '|', '^', '<<', '>>',
        // 'rlike', 'regexp', 'not regexp',
        // '~', '~*', '!~', '!~*', 'similar to',
                // 'not similar to',
    );

    /**
     * Create a new query builder instance.
     *
     * @param Connection $connection
     * @param Processor  $processor
     */
    public function __construct(Connection $connection, Processor $processor)
    {
        $this->grammar = new Grammar;
        $this->connection = $connection;
        $this->processor = $processor;
    }

    /**
     * Set the cursor timeout in seconds.
     *
     * @param  int $seconds
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set the cursor hint.
     *
     * @param  mixed $index
     * @return $this
     */
    public function hint($index)
    {
        $this->hint = $index;

        return $this;
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = [])
    {
        return $this->where('id', '=', urlencode($id))->first($columns);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function get($columns = [])
    {
        return $this->getFresh($columns);
    }

    /**
     * Execute the query as a fresh "select" statement.
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function getFresh($columns = [])
    {
        // If no columns have been specified for the select statement, we will set them
        // here to either the passed columns, or the standard default of retrieving
        // all of the columns on the table using the "wildcard" column character.
        if (is_null($this->columns))
        {
            $this->columns = $columns;
        }

        // Drop all columns if * is present, MongoDB does not work this way.
        if (in_array('*', $this->columns))
        {
            $this->columns = [];
        }

        $query = array();

        foreach ($this->selectComponents as $component)
        {
            if (! is_null($this->$component))
            {
                $method = 'compile' . ucfirst($component);

                $query[$component] = $this->$method();
            }
        }

        $url = $this->buildUrl('query', $query);

        return $this->connection->select($url);
    }

    public function buildUrl($method, $query)
    {
        return "/{$this->from}/{$method}?{$this->concatenate($query)}";
    }

    /**
     * Generate the unique cache key for the current query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $key = [
            'wheres'     => $this->wheres,
            'columns'    => $this->columns,
            'groups'     => $this->groups,
            'orders'     => $this->orders,
            'offset'     => $this->offset,
            'limit'      => $this->limit,
            'aggregate'  => $this->aggregate,
        ];

        return md5(serialize(array_values($key)));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = [])
    {
        $this->aggregate = compact('function', 'columns');

        $results = $this->get($columns);

        // Once we have executed the query, we will reset the aggregate property so
        // that more select queries can be executed against the database without
        // the aggregate value getting in the way when the grammar builds it.
        $this->columns = null;
        $this->aggregate = null;

        if (isset($results[0])) {
            $result = (array) $results[0];

            return $result['aggregate'];
        }
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return ! is_null($this->first());
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return Builder
     */
    public function distinct($column = false)
    {
        $this->distinct = true;

        if ($column) {
            $this->columns = [$column];
        }

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        if (is_string($direction)) {
            $direction = (strtolower($direction) == 'asc' ? 1 : -1);
        }

        if ($column == 'natural') {
            $this->orders['$natural'] = $direction;
        } else {
            $this->orders[$column] = $direction;
        }

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return Builder
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

        return $this;
    }

    /**
     * Set the limit and offset for a given page.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function forPage($page, $perPage = 15)
    {
        $this->paginating = true;

        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @param  array  $options
     * @return int
     */
    public function update(array $values, array $options = [])
    {
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [], array $options = [])
    {
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [], array $options = [])
    {
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return array
     */
    public function pluck($column, $key = null)
    {
        $results = $this->get(is_null($key) ? [$column] : [$column, $key]);

        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        return Arr::pluck(
            $results,
            $column,
            $key
        );
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
    }

    /**
     * Set the collection which the query is targeting.
     *
     * @param  string  $collection
     * @return Builder
     */
    public function from($collection)
    {
        return parent::from($collection);
    }

    /**
     * Run a truncate statement on the table.
     */
    public function truncate()
    {
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string  $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        return parent::lists($column, $key);
    }

    /**
     * Create a raw database expression.
     *
     * @param  closure  $expression
     * @return mixed
     */
    public function raw($expression = null)
    {
    }

    /**
     * Append one or more values to an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function push($column, $value = null, $unique = false)
    {
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function pull($column, $value = null)
    {
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->processor);
    }

    /**
     * Perform an update query.
     *
     * @param  array  $query
     * @param  array  $options
     * @return int
     */
    protected function performUpdate($query, array $options = [])
    {
    }


    public function compileAggregate()
    {
        $aggregate = $this->aggregate;
    }

    public function compileColumns()
    {
        if (! $this->columns)
        {
            return '';
        }

        return 'field=' . implode(',', $this->columns);
    }

    /**
     * Compile the where array.
     *
     * @return array
     */
    protected function compileWheres()
    {
        // The wheres to compile.
        $wheres = $this->wheres ?: [];

        $query = [];

        foreach ($wheres as $where)
        {
            $method = 'compileWhere' . ucfirst($where['type']);

            $where['value'] = urlencode($where['value']); // urlencode

            $where['column'] = $this->removeTableFromColumn($where['column']);

            $query[] = ($where['boolean'] == 'and' ? '' : '!') . $this->$method($where);
        }

        return implode('&', $query);
    }

    protected function compileWhereBasic($where)
    {
        $operator = $this->convertOperator($where['operator']);

        return $where['column'] . '=' . $operator . ':' . $where['value'];
    }

    protected function compileWhereNested($where)
    {
        return $query->compileWheres();
    }

    protected function compileWhereIn($where)
    {
        return $where['column'] . '=in:' . $where['value'];
    }

    protected function compileWhereNotIn($where)
    {

        return $where['column'] . '=nin:' . $where['value'];
    }

    protected function compileWhereNull($where)
    {

        return $where['column'] . '=null';
    }

    protected function compileWhereNotNull($where)
    {
        return $where['column'] . '=!null';
    }

    protected function compileWhereBetween($where)
    {
        $value = $where['value'];

        if ($where['not'])
        {
            return $where['column'] . '=!between:' . $value[0] . ',' . $value[1];
        }
        else
        {
            return $where['column'] . '=between:' . $value[0] . ',' . $value[1];
        }
    }

    protected function compileWhereRaw($where)
    {
        return $where['sql'];
    }


    protected function compileLimit()
    {
        return '_limit=' . $this->limit;
    }


    /**
     * remove '{$table}.' from column
     *
     * @param  mixed $id
     * @return mixed
     */
    public function removeTableFromColumn($column)
    {
        return str_replace($this->from . '.', '', $column);
    }

    public function convertOperator($operator)
    {
        if (isset($this->conversion[$operator]))
        {
            return $this->conversion[$operator];
        }

        return $operator;
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array   $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode('&', array_filter($segments, function($value)
        {
            return (string) $value !== '';
        }));
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }
}