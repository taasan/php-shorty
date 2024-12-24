<?php

declare(strict_types=1);

namespace App;

class Database
{
    private readonly string $_dsn;
    private readonly \PDO $_pdo;
    public readonly bool $IS_SQLITE;
    public readonly bool $IS_MYSQL;
    public readonly bool $IS_POSTGRES;

    public function __construct(string $dsn)
    {
        $this->_dsn = $dsn;
    }

    private function _init_pdo(): \PDO
    {
        $rp = new \ReflectionProperty(get_class($this), '_pdo');
        if (!$rp->isInitialized($this)) {
            $this->_pdo = new \PDO($this->_dsn);
            $driver_name = $this->_pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->IS_SQLITE = 'sqlite' === $driver_name;
            $this->IS_MYSQL = 'mysql' === $driver_name;
            $this->IS_POSTGRES = 'pgsql' === $driver_name;
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            if ($this->IS_SQLITE) {
                $this->_pdo->setAttribute(\PDO::SQLITE_ATTR_OPEN_FLAGS, \PDO::SQLITE_OPEN_READONLY);
            }
        }
        return $this->_pdo;
    }

    public function getPdo(): \PDO
    {
        return $this->_init_pdo();
    }
}
