<?php namespace Epsilon\Queryfly\Parser;


use Exception;
use Closure;

/**
 * A queryfly query string parser
 *
 *
 * @author xiangshouding
 */
class Query {

    protected $operators = [];

    protected $convertion = [
        'eq' => '=',
        '!eq' => '!=',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'like' => 'like',
        '!like' => 'not like',
        'between' => 'between',
        'in' => 'in',
        '!in' => 'not in'
    ];

    protected $functions = [
        'limit',
        'offset',
        'where',
        'offset',
        'notnull',
        'null'
    ];

    protected $query;

    protected $statement = [];

    public function __construct($query)
    {
        $this->query = (array) $query;
        $this->operators = array_keys($this->convertion);
    }

    /**
     * Parse Query to Model method
     *
     * @return \Epsilon\Queryfly\Parser\Query
     */
    public function parse()
    {
        $not = false;

        foreach ($this->query as $field => $statement)
        {

            if (strpos($field, '!') === 0)
            {
                $not = true;
                $field = substr($field, 1);
            }

            if (preg_match('/^_[^_]+/', $field, $match))
            {
                $field = strtolower($field);

                $field = substr($field, 1); // remove '_'

                if ($field === 'orderby')
                {
                    $this->parseFunctionOrderBy($statement);
                }
                else if ($field === 'field')
                {
                    $this->statement['get'] = explode(',', $statement);
                }
                else
                {
                    $this->parseFunction($field, $statement);
                }
            }
            else
            {
                $this->parseWhere($field, $statement);
            }
        }

        return $this;
    }

    public function parseFunction($function, $value)
    {
        if (!in_array($function, $this->functions)) return;

        $this->statement[$function] = $value;
    }

    /**
     * parse where from Query string.
     *
     * @param string $field
     * @param array|string $statement
     */
    public function parseWhere($field, $statement)
    {
        if (! is_array($statement))
        {
            $statement = [$statement];
        }

        foreach ($statement as $one)
        {
            list($op, $value) = $this->parseWhereStatement($one);

            if (!$op || !in_array($op, $this->operators)) continue;

            if ($op == 'in' || $op == '!in')
            {
                $value = explode(',', $value);
            }

            $op = $this->convertOperator($op);

            if (!isset($this->statement['where']))
            {
                $this->statement['where'] = [];
            }

            array_push($this->statement['where'], [$field, $op, $value]);    
        }

    }

    /**
     * parse 
     */
    public function parseFunctionOrderBy($statement)
    {

        collect(explode(',', $statement))->each(function($one)
        {
            list($field, $direction) = explode(':', $one);

            if (!isset($this->statement['orderBy']))
            {
                $this->statement['orderBy'] = [];
            }

            array_push($this->statement['orderBy'], [$field, $direction]);
        });
    }


    /**
     * bind Query statement to Model
     * 
     * @param mixed $model some Model instance
     * @param Closure $callback
     * @return mixed
     */
    public function bindToModel($model, Closure $callback = null)
    {
        // parse given  url query language.
        $this->parse();

        foreach ($this->statement as $function => $paramer)
        {
            if ($function === 'get') continue;

            foreach ((array) $paramer as $args)
            {
                $model = call_user_func_array(
                    array($model, $function),
                    (array) $args
                );
            }

        }

        if ($callback)
        {
            return $callback($this->getSelect(), $model);           
        }

        return $model;
    }

    /**
     * Get need field.
     * 
     * @return array
     */
    public function getSelect()
    {
        return isset($this->statement['get']) ? $this->statement['get'] : ['*'];
    }

    /**
     * convert operator to Model's operator.
     *
     * @param string $op
     * @return string
     */
    protected function convertOperator($op)
    {

        if (isset($this->convertion[$op]))
        {
            return $this->convertion[$op];
        }

        return $op;
    }

    /**
     * parse where sub statement.
     * 
     * @return array [op, value]
     */
    protected function parseWhereStatement($statement)
    {

        if ($p = strpos($statement, ':'))
        {
            return [
                substr($statement, 0, $p),
                substr($statement, $p + 1)
            ];
        }

        return [null, $statement];
    }
}
