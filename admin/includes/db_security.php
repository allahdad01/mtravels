<?php
// Start output buffering
ob_start();

/**
 * Database Security Module
 * 
 * This file provides enhanced security features for database operations
 * in the admin panel.
 */

/**
 * Class to handle database security
 */
class DbSecurity {
    /**
     * Sanitize an SQL identifier (table or column name)
     * 
     * @param string $identifier The identifier to sanitize
     * @return string The sanitized identifier
     */
    public static function sanitizeIdentifier($identifier) {
        // Only allow alphanumeric and underscore
        $identifier = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
        
        // Ensure it's not empty
        if (empty($identifier)) {
            throw new Exception("Invalid SQL identifier");
        }
        
        return $identifier;
    }
    
    /**
     * Create a secure PDO database connection
     * 
     * @param string $host Database host
     * @param string $dbname Database name
     * @param string $username Database username
     * @param string $password Database password
     * @return PDO The PDO connection
     */
    public static function createPdoConnection($host, $dbname, $username, $password) {
        try {
            // Set PDO options for security
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT => 5 // 5 second timeout
            ];
            
            // Create PDO connection
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
            
            return $pdo;
        } catch (PDOException $e) {
            // Log error but don't expose details
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Unable to connect to database. Please try again later.");
        }
    }
    
    /**
     * Secure query execution with prepared statements and parameter binding
     * 
     * @param PDO $pdo PDO connection
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement Executed statement
     */
    public static function executeQuery($pdo, $query, $params = []) {
        try {
            // Prepare statement
            $stmt = $pdo->prepare($query);
            
            // Bind parameters and execute
            $stmt->execute($params);
            
            return $stmt;
        } catch (PDOException $e) {
            // Log error with query and parameters
            $logParams = self::sanitizeLogParams($params);
            error_log("Database query error: " . $e->getMessage() . 
                     " | Query: " . $query . 
                     " | Params: " . json_encode($logParams));
            
            throw new Exception("Database operation failed. Please try again later.");
        }
    }
    
    /**
     * Execute a secure INSERT query and return the last insert ID
     * 
     * @param PDO $pdo PDO connection
     * @param string $table Table name
     * @param array $data Data to insert (column => value)
     * @return int Last insert ID
     */
    public static function insert($pdo, $table, $data) {
        // Sanitize table name
        $table = self::sanitizeIdentifier($table);
        
        // Build query
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $columnsStr = implode(', ', array_map(function($col) {
            return self::sanitizeIdentifier($col);
        }, $columns));
        
        $placeholdersStr = implode(', ', $placeholders);
        
        $query = "INSERT INTO {$table} ({$columnsStr}) VALUES ({$placeholdersStr})";
        
        // Execute query
        self::executeQuery($pdo, $query, array_values($data));
        
        // Return last insert ID
        return $pdo->lastInsertId();
    }
    
    /**
     * Execute a secure UPDATE query
     * 
     * @param PDO $pdo PDO connection
     * @param string $table Table name
     * @param array $data Data to update (column => value)
     * @param string $whereClause WHERE clause with placeholders
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public static function update($pdo, $table, $data, $whereClause, $whereParams = []) {
        // Sanitize table name
        $table = self::sanitizeIdentifier($table);
        
        // Build SET clause
        $setClauses = [];
        $setParams = [];
        
        foreach ($data as $column => $value) {
            $column = self::sanitizeIdentifier($column);
            $setClauses[] = "{$column} = ?";
            $setParams[] = $value;
        }
        
        $setClauseStr = implode(', ', $setClauses);
        
        // Build query
        $query = "UPDATE {$table} SET {$setClauseStr} WHERE {$whereClause}";
        
        // Combine parameters
        $params = array_merge($setParams, $whereParams);
        
        // Execute query
        $stmt = self::executeQuery($pdo, $query, $params);
        
        // Return affected rows
        return $stmt->rowCount();
    }
    
    /**
     * Execute a secure DELETE query
     * 
     * @param PDO $pdo PDO connection
     * @param string $table Table name
     * @param string $whereClause WHERE clause with placeholders
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public static function delete($pdo, $table, $whereClause, $whereParams = []) {
        // Sanitize table name
        $table = self::sanitizeIdentifier($table);
        
        // Build query
        $query = "DELETE FROM {$table} WHERE {$whereClause}";
        
        // Execute query
        $stmt = self::executeQuery($pdo, $query, $whereParams);
        
        // Return affected rows
        return $stmt->rowCount();
    }
    
    /**
     * Execute a secure SELECT query
     * 
     * @param PDO $pdo PDO connection
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param string $whereClause WHERE clause with placeholders
     * @param array $whereParams Parameters for WHERE clause
     * @param string $orderBy ORDER BY clause
     * @param int $limit LIMIT value
     * @param int $offset OFFSET value
     * @return array Query results
     */
    public static function select($pdo, $table, $columns = ['*'], $whereClause = '1=1', $whereParams = [], 
                                $orderBy = '', $limit = 0, $offset = 0) {
        // Sanitize table name
        $table = self::sanitizeIdentifier($table);
        
        // Sanitize columns
        $columnsStr = '*';
        if ($columns !== ['*']) {
            $columnsStr = implode(', ', array_map(function($col) {
                return self::sanitizeIdentifier($col);
            }, $columns));
        }
        
        // Build query
        $query = "SELECT {$columnsStr} FROM {$table} WHERE {$whereClause}";
        
        // Add ORDER BY if provided
        if (!empty($orderBy)) {
            // Basic sanitization - not foolproof but better than nothing
            $orderBy = preg_replace('/[^a-zA-Z0-9_\s,.]/', '', $orderBy);
            $query .= " ORDER BY {$orderBy}";
        }
        
        // Add LIMIT and OFFSET if provided
        if ($limit > 0) {
            $query .= " LIMIT " . intval($limit);
            
            if ($offset > 0) {
                $query .= " OFFSET " . intval($offset);
            }
        }
        
        // Execute query
        $stmt = self::executeQuery($pdo, $query, $whereParams);
        
        // Return results
        return $stmt->fetchAll();
    }
    
    /**
     * Sanitize log parameters to avoid logging sensitive data
     * 
     * @param array $params Parameters to sanitize
     * @return array Sanitized parameters
     */
    private static function sanitizeLogParams($params) {
        $sensitiveFields = ['password', 'token', 'secret', 'credit_card', 'cvv', 'ssn'];
        $sanitized = [];
        
        foreach ($params as $key => $value) {
            if (is_numeric($key)) {
                // For numeric keys, try to guess if it's sensitive
                $isSensitive = false;
                foreach ($sensitiveFields as $field) {
                    if (is_string($value) && stripos($value, $field) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }
                
                $sanitized[$key] = $isSensitive ? '[REDACTED]' : $value;
            } else {
                // For named keys, check the key name
                $isSensitive = false;
                foreach ($sensitiveFields as $field) {
                    if (stripos($key, $field) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }
                
                $sanitized[$key] = $isSensitive ? '[REDACTED]' : $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate and sanitize input based on type
     * 
     * @param mixed $input Input to validate
     * @param string $type Expected type (int, float, string, email, date)
     * @param array $options Additional validation options
     * @return mixed Sanitized input or null if invalid
     */
    public static function validateInput($input, $type, $options = []) {
        switch ($type) {
            case 'int':
                if (!is_numeric($input)) {
                    return null;
                }
                $value = intval($input);
                if (isset($options['min']) && $value < $options['min']) {
                    return null;
                }
                if (isset($options['max']) && $value > $options['max']) {
                    return null;
                }
                return $value;
                
            case 'float':
                if (!is_numeric($input)) {
                    return null;
                }
                $value = floatval($input);
                if (isset($options['min']) && $value < $options['min']) {
                    return null;
                }
                if (isset($options['max']) && $value > $options['max']) {
                    return null;
                }
                return $value;
                
            case 'string':
                if (!is_string($input) && !is_numeric($input)) {
                    return null;
                }
                $value = trim((string)$input);
                if (isset($options['maxlength']) && strlen($value) > $options['maxlength']) {
                    $value = substr($value, 0, $options['maxlength']);
                }
                return $value;
                
            case 'email':
                $value = filter_var($input, FILTER_VALIDATE_EMAIL);
                return $value;
                
            case 'date':
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                    return $input;
                }
                return null;
                
            case 'currency':
                if (!in_array(strtoupper($input), ['USD', 'AFS', 'EUR', 'DARHAM'])) {
                    return null;
                }
                return strtoupper($input);
                
            default:
                return null;
        }
    }
}
?> 