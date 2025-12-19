<?php
/**
 * Query Builder Class
 * Restaurant POS System
 */

namespace App\Database;

class QueryBuilder
{
    protected $db;
    protected $table;
    protected $select = ['*'];
    protected $joins = [];
    protected $where = [];
    protected $whereParams = [];
    protected $groupBy = [];
    protected $having = [];
    protected $orderBy = [];
    protected $limit = null;
    protected $offset = null;
    protected $union = [];
    protected $lock = null;

    public function __construct(Database $db, $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    /**
     * Set select columns
     */
    public function select($columns = ['*'])
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }
        $this->select = $columns;
        return $this;
    }

    /**
     * Add select column
     */
    public function addSelect($column)
    {
        $this->select[] = $column;
        return $this;
    }

    /**
     * Set distinct
     */
    public function distinct()
    {
        $this->select = array_merge(['DISTINCT'], $this->select);
        return $this;
    }

    /**
     * Add join
     */
    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    /**
     * Add left join
     */
    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add right join
     */
    public function rightJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add where condition
     */
    public function where($column, $operator, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = ':where_' . count($this->whereParams);
        $this->where[] = "{$column} {$operator} {$paramName}";
        $this->whereParams[$paramName] = $value;

        return $this;
    }

    /**
     * Add where with OR
     */
    public function orWhere($column, $operator, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = ':orwhere_' . count($this->whereParams);
        $this->where[] = "OR {$column} {$operator} {$paramName}";
        $this->whereParams[$paramName] = $value;

        return $this;
    }

    /**
     * Add where in condition
     */
    public function whereIn($column, array $values)
    {
        $placeholders = [];
        foreach ($values as $value) {
            $paramName = ':wherein_' . count($this->whereParams);
            $placeholders[] = $paramName;
            $this->whereParams[$paramName] = $value;
        }

        $this->where[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    /**
     * Add where not in condition
     */
    public function whereNotIn($column, array $values)
    {
        $placeholders = [];
        foreach ($values as $value) {
            $paramName = ':wherenotin_' . count($this->whereParams);
            $placeholders[] = $paramName;
            $this->whereParams[$paramName] = $value;
        }

        $this->where[] = "{$column} NOT IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    /**
     * Add where null condition
     */
    public function whereNull($column)
    {
        $this->where[] = "{$column} IS NULL";
        return $this;
    }

    /**
     * Add where not null condition
     */
    public function whereNotNull($column)
    {
        $this->where[] = "{$column} IS NOT NULL";
        return $this;
    }

    /**
     * Add between condition
     */
    public function whereBetween($column, $min, $max)
    {
        $minParam = ':between_min_' . count($this->whereParams);
        $maxParam = ':between_max_' . count($this->whereParams);
        
        $this->whereParams[$minParam] = $min;
        $this->whereParams[$maxParam] = $max;
        
        $this->where[] = "{$column} BETWEEN {$minParam} AND {$maxParam}";
        return $this;
    }

    /**
     * Add group by
     */
    public function groupBy($columns)
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Add having condition
     */
    public function having($column, $operator, $value)
    {
        $paramName = ':having_' . count($this->whereParams);
        $this->having[] = "{$column} {$operator} {$paramName}";
        $this->whereParams[$paramName] = $value;
        return $this;
    }

    /**
     * Add order by
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    /**
     * Add random order
     */
    public function inRandomOrder()
    {
        $this->orderBy[] = 'RAND()';
        return $this;
    }

    /**
     * Set limit
     */
    public function limit($value)
    {
        $this->limit = $value;
        return $this;
    }

    /**
     * Set offset
     */
    public function offset($value)
    {
        $this->offset = $value;
        return $this;
    }

    /**
     * Set lock mode
     */
    public function lock($value = true)
    {
        $this->lock = $value;
        return $this;
    }

    /**
     * Get first result
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get all results
     */
    public function get()
    {
        $sql = $this->toSql();
        return $this->db->fetchAll($sql, $this->whereParams);
    }

    /**
     * Count results
     */
    public function count($column = '*')
    {
        $originalSelect = $this->select;
        $this->select = ["COUNT({$column}) as count"];
        
        $result = $this->first();
        
        $this->select = $originalSelect;
        
        return $result ? (int) $result->count : 0;
    }

    /**
     * Check if record exists
     */
    public function exists()
    {
        return $this->count() > 0;
    }

    /**
     * Insert data
     */
    public function insert(array $data)
    {
        if (isset($data[0]) && is_array($data[0])) {
            // Multiple insert
            $columns = array_keys($data[0]);
            $placeholders = [];
            $params = [];
            
            foreach ($data as $row) {
                $rowPlaceholders = [];
                foreach ($row as $column => $value) {
                    $paramName = ':insert_' . count($params);
                    $rowPlaceholders[] = $paramName;
                    $params[$paramName] = $value;
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $placeholders);
        } else {
            // Single insert
            $columns = array_keys($data);
            $placeholders = [];
            $params = [];
            
            foreach ($data as $column => $value) {
                $paramName = ':insert_' . count($params);
                $placeholders[] = $paramName;
                $params[$paramName] = $value;
            }
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        }
        
        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Update data
     */
    public function update(array $data)
    {
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $paramName = ':update_' . count($params);
            $setClause[] = "{$column} = {$paramName}";
            $params[$paramName] = $value;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause);
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
            $params = array_merge($params, $this->whereParams);
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
            $params = $this->whereParams;
        } else {
            $params = [];
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Build SQL query
     */
    public function toSql()
    {
        $sql = "SELECT " . implode(', ', $this->select);
        $sql .= " FROM {$this->table}";
        
        // Add joins
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        // Add where conditions
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        // Add group by
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }
        
        // Add having
        if (!empty($this->having)) {
            $sql .= " HAVING " . implode(' AND ', $this->having);
        }
        
        // Add order by
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // Add limit
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        // Add offset
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }

    /**
     * Get the underlying database instance
     */
    public function getDatabase()
    {
        return $this->db;
    }
}