<?php

class Database
{
    // Properties for PDO instance and error handling
    private $pdo;

    private $errorMessage;

    private $errorCode;

    private $rowCount;

    private $affectedRows;

    // Database connection parameters
    private $host;

    private $dbname;

    private $username;

    private $password;

    // Static properties for tracking all PDO instances and query statistics
    private static $totalQueryCount = 0;

    private static $totalQueryTime = 0.0;

    private static $pdoInstances = [];  // Array to store the PDO instances

    // Constructor to initialize connection parameters
    public function __construct($host, $dbname, $username, $password)
    {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
    }

    // Method to establish a PDO connection
    private function connect()
    {
        // Create a unique key for this connection
        $pdoKey = md5($this->host . $this->dbname . $this->username . $this->password);

        // Check if this PDO instance already exists
        if (!isset(self::$pdoInstances[$pdoKey])) {
            try {
                // Create a new PDO instance with error mode set to exception
                $pdo = new PDO("mysql:host=$this->host;dbname=$this->dbname;charset=" . ENC_CHARSET, $this->username, $this->password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdoInstances[$pdoKey] = $pdo;
            } catch (PDOException $e) {
                // Handle PDO connection error
                $this->errorMessage = $e->getMessage();
                $this->errorCode = $e->getCode();
                return false;
            }
        }

        // Set the current PDO instance
        $this->pdo = self::$pdoInstances[$pdoKey];
        return true;
    }

    // Method to ensure a valid PDO connection
    private function ensureConnection()
    {
        if ($this->pdo === null && !$this->connect()) {
            return false;  // Return false if connection failed
        }
        return true;
    }

    // Execute an SQL query with optional parameters
    public function executeSQL($sql, $params = [])
    {
        // Ensure a valid connection before executing the query
        $this->ensureConnection();

        // Trim leading spaces from SQL query
        $sql = ltrim($sql);

        try {
            // Measure query execution time
            $startTime = microtime(true);
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                // Handle errors differently based on the environment
                if (isLocalhostOrLocalIP()) {
                    $errorInfo = $this->pdo->errorInfo();
                    echo 'Error shown only at localhost: ' . $errorInfo[2] . " (SQL: $sql)";
                    exit;
                } else {
                    $errorInfo = $this->pdo->errorInfo();
                    echo 'Error: 49854';  // Generic error message for production
                    exit;
                }
            }
            $stmt->execute($params);
            $endTime = microtime(true);

            // Update query statistics
            self::$totalQueryCount++;
            self::$totalQueryTime += ($endTime - $startTime);

            $this->errorCode = 0;

            // Process result based on query type
            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0) {
                // Handle SELECT queries
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->rowCount = count($result);
                return $result;
            } else {
                // Handle other query types
                $this->affectedRows = $stmt->rowCount();
                return true;
            }
        } catch (PDOException $e) {
            // Handle PDO execution errors
            $this->errorMessage = $e->getMessage();
            $this->errorCode = $e->getCode();
            if (isLocalhostOrLocalIP()) {
                echo 'ERROR shown only at localhost: ' . $this->getError() . " (SQL: $sql)" . '<br>';
            }

            return false;
        }
    }

    public function getLastInsertID()
    {
        return $this->pdo->lastInsertId();
    }

    public function getRowCount()
    {
        return $this->rowCount;
    }

    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    public function getError()
    {
        return $this->errorMessage;
    }

    public function getCode()
    {
        return $this->errorCode;
    }

    public function createTable($tableName, $columns)
    {
        $this->ensureConnection();
        try {
            $sql = "CREATE TABLE $tableName (";
            foreach ($columns as $column) {
                $sql .= "$column, ";
            }
            $sql = rtrim($sql, ', ');

            if (defined('CLASS_DATABASE_USE_SQLITE3'))
                $sql .= ')';
            else
                $sql .= ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';

            return $this->executeSQL($sql);
        } catch (PDOException $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorCode = $e->getCode();
            return false;
        }
    }

    public function resetTable($tableName, $tableColumns)
    {
        $this->ensureConnection();
        try {
            // for some reason I must delete first, if it contains a lot of data.
            // If you try to drop a table that has data in it without deleting the data first, you may encounter errors such as foreign key constraint violations or duplicate key errors. This is because the data in the table may reference other tables or have unique constraints that are no longer valid after the table has been dropped.
            $sql = "DELETE FROM $tableName";
            $this->executeSQL($sql);

            $sql = "DROP TABLE IF EXISTS $tableName";
            $this->executeSQL($sql);

            $this->createTable($tableName, $tableColumns);
            return true;
        } catch (PDOException $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorCode = $e->getCode();
            return false;
        }
    }

    public function dropTable($tableName)
    {
        $this->ensureConnection();
        try {
            // for some reason I must delete first, if it contains a lot of data.
            // If you try to drop a table that has data in it without deleting the data first, you may encounter errors such as foreign key constraint violations or duplicate key errors. This is because the data in the table may reference other tables or have unique constraints that are no longer valid after the table has been dropped.
            $sql = "DELETE FROM $tableName";
            $this->executeSQL($sql);

            $sql = "DROP TABLE IF EXISTS $tableName";
            $this->executeSQL($sql);

            return true;
        } catch (PDOException $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorCode = $e->getCode();
            return false;
        }
    }

    public static function getTotalQueryCount()
    {
        return self::$totalQueryCount;
    }

    public static function getTotalQueryTime()
    {
        return self::$totalQueryTime;
    }
}
