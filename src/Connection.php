<?php namespace Epsilon\Queryfly;

use Illuminate\Database\Connection as BaseConnection;

class Connection extends BaseConnection
{
    /**
     * The MongoDB database handler.
     *
     * @var \MongoDB\Database
     */
    protected $db;

    /**
     * The MongoDB connection handler.
     *
     * @var \MongoDB\Client
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param  array   $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        
        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }


    /**
     * Get a schema builder instance for the connection.
     *
     * @return Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }


    public function select($query, $bindings = array())
    {
        $url = $this->getDsn($this->config) . $query;

        $request = new Request('GET', $url);

        return $request->request();
    }

    public function insert($query, $bindings = array())
    {
        $url = $this->getDsn($this->config) . $query;

        $request = new Request('POST', $url, $bindings);

        return $request->request();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar;
    }

    /**
     * Get the default post processor instance.
     *
     * @return Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor;
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string  $table
     * @return Query\Builder
     */
    public function table($table)
    {
        $query = new Query\Builder($this, $this->getPostProcessor());

        return $query->from($table);
    }

    /**
     * Create a new HTTP request.
     *
     * @param  string  $dsn
     * @param  array   $config
     * @param  array   $options
     * @return MongoDB
     */
    protected function createConnection($dsn, array $config, array $options)
    {
        // http client
        var_dump(__FUNCTION__);
    }

    /**
     * Disconnect from the HTTP server
     */
    public function disconnect()
    {
        // unset($this->connection);
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @param  array   $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        // First we will create the basic DSN setup as well as the port if it is in
        // in the configuration options. This will give us the basic DSN we will
        // need to establish the MongoDB and return them back for use.

        $dsn = $config['dsn'];
        $host = $config['host'];
        $protocol = $config['protocol'];
        $database = $config['database'];
        $prefix = $config['prefix'] ?: '/api';

        // Check if the user passed a complete dsn to the configuration.
        if (! empty($dsn)) {
            return $dsn;
        }

        // Treat host option as array of hosts
        $hosts = is_array($host) ? $host : [$host];
        foreach ($hosts as &$host) {
            // Check if we need to add a port to the host
            if (strpos($host, ':') === false and isset($port)) {
                $host = "{$host}:{$port}";
            }
        }

        $dsn = "{$protocol}://" . $hosts[array_rand($hosts)] . "{$prefix}/{$database}";
        // Log::debug($dsn);
        return $dsn;
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int    $start
     * @return float
     */
    public function getElapsedTime($start)
    {
        return parent::getElapsedTime($start);
    }


    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getDriverName()
    {
        return 'qeuryfly';
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->db, $method], $parameters);
    }
}