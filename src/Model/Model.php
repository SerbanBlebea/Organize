<?php

namespace SerbanBlebea\Organize\Model;

use SerbanBlebea\Organize\Organize;
use Exception;

class Model extends Organize
{

    /**
     * Instance of the connector from Connector class
     *
     * @var object
     */
    private $connector;

    /**
     * Schema is a string that contains the conditions
     * for CRUD operations.
     *
     * @var string
     */
    private $schema = "";

    /**
     * Schema that holds the sort functionality
     *
     * @var string
     */
    private $sortSchema = "";

    /**
     * Schema that holds the keys for the values that
     * that needs inserting or updated in the Database
     *
     * @var string
     */
    private $insertKeySchema = "";

    /**
     * Schema that holds the values for the insert or
     * update querys
     *
     * @var string
     */
    private $insertValueSchema = "";

    /**
     * Create schema for updating rows in database
     *
     * @var string
     */
    private $updateSchema = "";

    /**
     * Construct method requires an instance of
     * Framework\Database\Connector supplied by the Injector
     *
     * @var object
     */
    public function __construct()
    {
        $connector = $this->getConnector();
        $this->connector = $connector->getConnector();
    }

    public function hasMany($class)
    {
        $classInstance = new $class();

        $parent_key = $classInstance->getTableName() . "." . $classInstance->getTableForeignKey($this->getTableName());
        $child_key = $this->getTableName() . "." . $this->getTableKey();

        return $this->connector
                    ->query("SELECT " . $classInstance->getTableName() . ".* FROM " . $classInstance->getTableName() .
                            " JOIN " . $this->getTableName() . " ON " . $parent_key . " = " . $child_key .
                            " WHERE " . $child_key . "=" . $this->id)
                    ->fetchAll(\PDO::FETCH_CLASS, $class);
    }

    // "SELECT * FROM table1 JOIN table2 ON own_key = foreign_key WHERE own_key = 1"
    public function belongsTo($class)
    {
        $classInstance = new $class();

        $parent_key = $classInstance->getTableName() . "." . $classInstance->getTableKey();
        $child_key = $this->getTableName() . "." . $this->getTableForeignKey($classInstance->getTableName());

        return $this->connector
                    ->query("SELECT " . $classInstance->getTableName() . ".* FROM " . $classInstance->getTableName() .
                            " JOIN " . $this->getTableName() . " ON " . $parent_key . " = " . $child_key .
                            " WHERE " . $parent_key . " = " . $this->id)
                    ->fetchAll(\PDO::FETCH_CLASS, $class);
    }

    public function manyHasMany()
    {

    }

    /**
     * This is the method that let's you chain conditions
     * before executing the database query.
     *
     * @var strings
     * @return self
     */
    public function where($valueA, $operand = null, $valueB)
    {
        if($this->schema == "")
        {
            $this->schema .= $valueA . $operand . "'" . $valueB . "'";
        } else {
            $this->schema .= " AND " . $valueA . $operand . "'" . $valueB . "'";
        }
        return $this;
    }

    /**
     * The same as "where" but introduces the OR element in the query.
     *
     * @var strings
     * @return self
     */
    public function orWhere($valueA, $operand, $valueB)
    {
        if($this->schema == "")
        {
            $this->schema .= $valueA . $operand . "'" . $valueB . "' OR ";
        } else {
            $this->schema .= " OR " . $valueA . $operand . "'" . $valueB . "'";
        }
        return $this;
    }

    /**
     * Insert data in the database
     *
     * @param array of keys and values
     * @return boolean
     */
    public function create(array $array)
    {
        $this->createSchema($array);
        return $statement = $this->connector->prepare("INSERT INTO " . $this->getTableName() . " ( " . $this->insertKeySchema . " ) VALUES ( " . $this->insertValueSchema . " )")->execute();
    }

    /**
     * Create the schema for the Create or Update functions
     *
     * @return null
     */
    public function createSchema(array $array)
    {
        $insertKeySchema = "";
        $insertValueSchema = "";
        $i = 0;

        /*
        $array = array_filter($array, function($index)
        {
            return array_search($index, $this->editables) > -1;
        }, ARRAY_FILTER_USE_KEY);
        */

        foreach($array as $index => $item)
        {
            if($i < count($array) - 1)
            {
                $insertKeySchema .= $index . ', ';
                $insertValueSchema .= "'" . $item . "', ";
            } else {
                $insertKeySchema .= $index;
                $insertValueSchema .= "'" . $item . "'";
            }
            $i++;
        }
        $this->insertKeySchema = $insertKeySchema;
        $this->insertValueSchema = $insertValueSchema;
    }

    /**
     * Update entry in the database
     *
     * @param array of keys and values
     * @return boolean
     */
    public function update(array $array)
    {
        $this->updateSchema($array);
        if($this->schema == "")
        {
            $this->schema = "id = '" . $this->id . "'";
        }
        return $statement = $this->connector->prepare("UPDATE " . $this->getTableName() . " SET " . $this->updateSchema . " WHERE " . $this->schema)->execute();
    }

    /**
     * Create the schema that holds the key = value for update
     *
     * @param array of keys and values
     * @return bull
     */
    private function updateSchema(array $array)
    {
        $updateSchema = '';
        $i = 0;
        foreach($array as $index => $item)
        {
            if($i < count($array) - 1)
            {
                $updateSchema .= $index . "= '" . $item . "', ";

            } else {
                $updateSchema .= $index . "= '" . $item . "'";
            }
            $i++;
        }
        $this->updateSchema = $updateSchema;
    }

    /**
     * Get one single row from the database and instantiate the model
     *
     * @return object of model
     */
    public function selectOne()
    {
        if($this->schema == "")
        {
            throw new Exception("No conditions where selected");
        }
        return $this->connector->query("SELECT * FROM " . $this->getTableName() . " WHERE " . $this->schema)->fetchObject(get_called_class());
    }

    /**
     * Get all rows that corespond to the "schema"
     *
     * @return array of objects / object
     */
    public function select()
    {
        if($this->schema == "")
        {
            throw new Exception("No conditions where selected");
        }
        return $this->connector->query("SELECT * FROM " . $this->getTableName() . " WHERE " . $this->schema . $this->sortSchema)->fetchAll(\PDO::FETCH_CLASS, get_called_class());
    }

    /**
     * Get all rows in the database, no conditions
     *
     * @return array of objects
     */
    public function selectAll()
    {
        return $this->connector->query("SELECT * FROM " . $this->getTableName() . $this->sortSchema)->fetchAll(\PDO::FETCH_CLASS, get_called_class());
    }

    /**
     * Add sorting features to the query
     *
     * @param $sortBy string
     * @param $order string
     * @return sorted array of objects
     */
    public function sortBy($sortBy, $order)
    {
        $sortSchema = "";
        if(in_array($order, ["ASC", "DESC"]) == false)
        {
            throw new Exception("Invalid sorting params.");
        }
        $this->sortSchema = " ORDER BY " . $sortBy . " " . strtoupper($order);
        return $this;
    }

    /**
     * Delete row from database
     *
     * @return null
     */
    public function delete()
    {
        if($this->schema == "")
        {
            //throw new Exception("No conditions where selected");
            $this->schema = "id = '" . $this->id . "'";
        }
        return $statement = $this->connector->prepare("DELETE FROM " . $this->getTableName() . " WHERE " . $this->schema)->execute();
    }
}
