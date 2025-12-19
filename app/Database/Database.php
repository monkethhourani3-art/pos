<?php
/**
 * Database Class
 * Restaurant POS System
 */

namespace App\Database;

use PDO;
use PDOException;

class Database
{
    protected $pdo;
    protected $config;
    protected $transactions = 0;

    public function __construct()
    {
        $this->config = require CONFIG_PATH . '/database.php';
        $this->connect();
    }

    /**
     * Connect to database
     */
    protected function connect()
    {
        try {
            $connection = $this->config['connections'][$this->config['default']];
            
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['driver'],
                $connection['host'],
                $connection['port'],
                $connection['database'],
                $connection['charset']
            );
            
            $this->pdo = new PDO(
                $dsn,
                $connection['username'],
                $connection['password'],
                $connection['options']
            );
            
        } catch (PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get PDO instance
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Execute a query
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception('Query failed: ' . $e->getMessage());
        }
    }

    /**
     * Get a single row
     */
    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Get multiple rows
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get table instance for fluent queries
     */
    public function table($table)
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Insert a record
     */
    public function insert($table, $data)
    {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete($table, $where, $whereParams = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        if ($this->transactions == 0) {
            $this->pdo->beginTransaction();
        }
        $this->transactions++;
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        $this->transactions--;
        if ($this->transactions == 0) {
            $this->pdo->commit();
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        if ($this->transactions > 0) {
            $this->pdo->rollback();
            $this->transactions = 0;
        }
    }

    /**
     * Get the last insert ID
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Check if table exists
     */
    public function tableExists($table)
    {
        $stmt = $this->query("SHOW TABLES LIKE ?", [$table]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get table schema
     */
    public function getTableSchema($table)
    {
        return $this->fetchAll("DESCRIBE {$table}");
    }

    /**
     * Execute raw SQL
     */
    public function raw($sql)
    {
        return $this->pdo->exec($sql);
    }

    /**
     * Get database version
     */
    public function version()
    {
        return $this->fetchOne("SELECT VERSION() as version")['version'];
    }
}