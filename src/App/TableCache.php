<?php


namespace App;


use Psr\SimpleCache\CacheInterface;
use Swoole\Table;

class TableCache implements CacheInterface
{
    /**
     * @var Table
     */
    private $_table;

    /**
     * Cache constructor.
     * @param int $rows 缓存最大行数，默认 1024 行
     */
    public function __construct($rows = 1024)
    {
        $table = new Table($rows);
        $table->column('value', Table::TYPE_STRING, 500);
        $table->create();
        $this->_table = $table;
    }

    public function clear()
    {
        foreach ($this->_table as $key => $row) {
            $this->_table->del($key);
        }
    }

    public function getMultiple($keys, $default = null)
    {
        $rs = [];
        foreach ($keys as $key) {
            $rs[$key] = $this->get($key, $default);
        }
        return $rs;
    }

    public function get($key, $default = null)
    {
        $value = $this->_table->get($key);
        if ($value === false) {
            return $default;
        } else {
            return $value['value'];
        }
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    public function set($key, $value, $ttl = null)
    {
        return $this->_table->set($key, ['value' => $value]);
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function delete($key)
    {
        return $this->_table->del($key);
    }

    public function has($key)
    {
        return $this->_table->exist($key);
    }

}
