<?php

class Database
{
    // Encoding charset for database tables
    private $encCharset = "utf8mb4";

    // Properties for PDO instance and error handling
    private $pdo;

    // Query result info
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

    // To store error messages
    private $errorPrefix = '';

    // Static! Because we may have several instances recording their own errors.
    // We need one shared error stack.
    private static $errorStack = [];

    // Constructor to initialize connection parameters
    public function __construct($host, $dbname, $username, $password)
    {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
    }

    private function addErrorToStack($message)
    {
        $this->errorStack[] = "(".$this->errorPrefix. ') '. $message;
    }
    // We collect all errors, and you do whatever you want with them,
    // preferably at the end of your script, print all errors
    // if you are on local development server
    public function getErrorStack()
    {
        return $this->errorStack;
    }

    // Method to establish a PDO connection
    private function connect()
    {
        // Create a unique key for this connection
        $pdoKey = md5($this->host . $this->dbname . $this->username . $this->password);

        // To prefix the error so we know in what context it occured
        $this->errorPrefix = "DB Error ($this->host:$this->dbname:$this->username)";

        // Check if this PDO instance already exists
        if (!isset(self::$pdoInstances[$pdoKey])) {
            try {

                //we can globally have defined a constant too
                if (defined('ENC_CHARSET')) {
                    $encCharset = ENC_CHARSET;
                } else {
                    $encCharset = $this->encCharset;
                }

                // Create a new PDO instance with error mode set to exception
                //TODO: May not be necessary to set charset on connect, or is it?...
                //--Encoding errors can be horrifying.
                $pdo = new PDO("mysql:host=$this->host;dbname=$this->dbname;charset=" . $encCharset, $this->username, $this->password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdoInstances[$pdoKey] = $pdo;
            } catch (PDOException $e) {
                // Handle PDO connection error
                $this->addErrorToStack('Database->connect() exception: [Error code:]' . $e->getCode() . ' [Error message:] ' . $e->getMessage());

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
                $errorInfo = $this->pdo->errorInfo();
                $this->addErrorToStack('PDO->prepare returned false. PDO->errorInfo: [SQLSTATE] ' . $errorInfo[0] . ' [Driver-specific error code] ' . $errorInfo[1] . ' [Driver-specific error message] ' . $errorInfo[2]." [SQL query:] $sql");
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
          
            $this->addErrorToStack('Database->executeSQL() exception: [Error code:]' . $e->getCode() . ' [Error message:] ' . $e->getMessage())." [SQL query:] $sql";

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

    public static function getTotalQueryCount()
    {
        return self::$totalQueryCount;
    }

    public static function getTotalQueryTime()
    {
        return self::$totalQueryTime;
    }

    //Skip these stinky functions.
    //Random code using the database should not extract error info and throw around
    //Our database functions returns false on failure, and the caller should
    //create a central place for logging or printing errors by requesting the error stack.
    //Furthermore, any failure should be handled by this database module, and if it really
    //can not fix it, then store the appropriate error message and return false.
    //if needed, we can defined our own error codes to send to the caller,
    //but we won't straight out dump the sensetive error data to the caller,
    //as if would be supposed to deal with the details there and then. Again, we deal with it
    //internally, and then we provide the error-stack to a later caller (getErrorStack()).

    public function getError()
    {
        echo "DEPRECATED: Database->getError()";
        exit;
    }

    public function getCode()
    {
        echo "DEPRECATED: Database->getCode()";
        exit;
    }


}
