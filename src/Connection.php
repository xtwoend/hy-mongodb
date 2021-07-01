<?php

namespace Xtwoend\HyMongo;

use MongoDB\Client;
use Hyperf\Pool\Pool;
use Hyperf\Utils\Arr;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use MongoDB\Driver\Exception\Exception;
use Hyperf\Contract\ConnectionInterface;
use Xtwoend\HyMongo\Traits\DbConnection;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Exception\ConnectionException;
use MongoDB\Driver\Exception\RuntimeException;
use Xtwoend\HyMongo\Exception\MongoDBException;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\InvalidArgumentException;
use Hyperf\Database\ConnectionInterface as DbConnectionInterface;

class Connection extends BaseConnection implements ConnectionInterface, DbConnectionInterface
{
    use DbConnection;
    
    /**
     * @var Client
     */
    protected $connection;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $db;

    /**
     * Query Log
     */
    protected $logger;

    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $pool;

    public function __construct(ContainerInterface $container, Pool $pool, array $config)
    {
        parent::__construct($container, $pool);
        $this->config = $config;
        $this->pool = $pool;
        $this->reconnect();
        $this->logger = $container->get(LoggerFactory::class)->get('mongodb');
    }

    public function __call($name, $arguments)
    {
        if(method_exists($this, $name)){
            return call_user_func_array($name, $arguments);
        }

        return $this->connection->{$name}(...$arguments);
    }

    public function getActiveConnection()
    {
        // TODO: Implement getActiveConnection() method.
        if ($this->check()) {
            return $this;
        }
        if (!$this->reconnect()) {
            throw new ConnectionException('Connection reconnect failed.');
        }
        return $this;
    }

    /**
     * Reconnect the connection.
     */
    public function reconnect(): bool
    {   
        $this->close();

        // TODO: Implement reconnect() method.
        try {
            /**
             * https://docs.mongodb.com/php-library/v1.8/reference/class/MongoDBClient/
             */
            //mode
            $mode = $this->config['mode'];
            $config = $this->config['settings'][$mode];
            if (!$config) {
                throw new InvalidArgumentException("mode [$mode] Configuration not obtained");
            }

            //user password
            $username = $config['username'];
            $password = $config['password'];
            if (!empty($username) && !empty($password)) {
                $authStr = "$username:$password@";
            } else {
                $authStr = "";
            }
            //host
            $hosts = $config['host'];
            $ports = $config['port'];
            is_string($hosts) && $hosts = [$hosts];
            $urls = [];
            foreach ($hosts as $i => $host) {
                $port = is_array($ports) ? $ports[$i]: $ports;
                $urls[] = "$host:$port";
            }
            $urlsStr = implode(',', $urls);
            //db
            $database = $config['db'];
            $dbStr = "/$database";

            //Assembly
            $uri = "mongodb://{$authStr}{$urlsStr}{$dbStr}";

            $urlOptions = [];
            //data set
            $replica = isset($config['replica']) ? $config['replica'] : null;
            if ($replica) {
                $urlOptions['replicaSet'] = $replica;
            }
            //Reading preference
            $readPreference = isset($config['readPreference']) ? $config['readPreference'] : null;
            if ($readPreference) {
                $urlOptions['readPreference'] = $readPreference;
            }
            $options =  $this->config['options'];
            // connection
            $this->connection = new Client($uri, $urlOptions, $options);
        
            // instance db
            $this->db = $this->connection->selectDatabase($database);

        } catch (InvalidArgumentException $e) {
            throw MongoDBException::managerError('mongodb Connection parameter error:' . $e->getMessage());
        } catch (RuntimeException $e) {
            throw MongoDBException::managerError('mongodb uri format error:' . $e->getMessage());
        }
        $this->lastUseTime = microtime(true);
        return true;
    }

    /**
     * Close the connection.
     */
    public function close(): bool
    {
        // TODO: Implement close() method.
        return true;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function release(): void
    {
        parent::release();
    }

    /**
     * Determine whether the current database connection has timed out
     *
     * @return bool
     * @throws \MongoDB\Driver\Exception\Exception
     * @throws MongoDBException
     */
    public function check(): bool
    {
        try {
            $this->db->command(['ping' => 1]);
            return true;
        } catch (\Throwable $e) {
            return $this->catchMongoException($e);
        }
    }

     /**
     * Begin a fluent query against a database collection.
     * @param string $collection
     * @return Query\Builder
     */
    public function collection($collection)
    {
        $query = new Query\Builder($this, $this->getPostProcessor());

        return $query->from($collection);
    }

    /**
     * Get a MongoDB collection.
     * @param string $name
     * @return Collection
     */
    public function getCollection($name)
    {
        return new Collection($this, $this->db->selectCollection($name));
    }

    /**
     * log query 
     *
     * @return void
     */
    public function logQuery($queryString, $bindings, $time)
    {
        if ($this->config['log_query']) {
            $this->logger->info(sprintf('[%s] %s', $time, $queryString));
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     */
    public function getElapsedTime(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getQueryGrammar()
    {
        return new Query\Grammar();
    }
    
    /**
     * Undocumented function
     *
     * @return void
     */
    public function getPostProcessor()
    {
        return new Query\Processor();
    }

    /**
     * @param \Throwable $e
     * @return bool
     * @throws MongoDBException
     */
    private function catchMongoException(\Throwable $e)
    {
        switch ($e) {
            case ($e instanceof InvalidArgumentException):
            {
                throw MongoDBException::managerError('mongo argument exception: ' . $e->getMessage());
            }
            case ($e instanceof AuthenticationException):
            {
                throw MongoDBException::managerError('Mongo database connection authorization failed:' . $e->getMessage());
            }
            case ($e instanceof ConnectionException):
            {
                /**
                 * https://cloud.tencent.com/document/product/240/4980
                 * If there is a connection failure, then reconnect
                 */
                for ($counts = 1; $counts <= 5; $counts++) {
                    try {
                        $this->reconnect();
                    } catch (\Exception $e) {
                        continue;
                    }
                    break;
                }
                return true;
            }
            case ($e instanceof RuntimeException):
            {
                throw MongoDBException::managerError('mongo runtime exception: ' . $e->getMessage());
            }
            default:
            {
                throw MongoDBException::managerError('mongo unexpected exception: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get the database connection name.
     *
     * @return null|string
     */
    public function getName()
    {
        return $this->pool->getName();
    }
}