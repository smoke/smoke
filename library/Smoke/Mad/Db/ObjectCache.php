<?php
/**
 * Simple object registry used for database schema cache
 *  
 * @author Radoslav Kirilov
 */
class Smoke_Mad_Db_ObjectCache
{
    /**
     * Storage array
     * @var array
     */
    private $_storage = null;

    public function __construct()
    {
        $this->_storage = new ArrayObject();
    }

    /**
     * Setter method, basically same as offsetSet().
     * 
     * @param string $index The location in the ArrayObject in which to store the value
     * @param mixed $value The object to store in the ArrayObject.
     * @return void
     */
    public function set($index, $value)
    {
        $this->_storage->offsetSet($index, $value);
    }

    /**
     * Getter method, basically same as offsetGet().
     *
     * string $index - get the value associated with $index
     * @return mixed
     */
    public function get($index)
    {
        return $this->_storage->offsetGet($index);
    }
}
