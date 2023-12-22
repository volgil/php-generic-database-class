# Generic database class for php

When you have instantiated an object of this database class, it will connect to the database when you execute the first query. Then it will keep the connection in a pool of connections for future use.

Uses prepared statements.

## Repo purpose

This repo is created to encourage myself to better modularize the generic components I write for personal projects.

## Repo goal

Create a database module easy to incorporate in small PHP projects.

## Version

Version 0.1.0.

## Install

Include Database.php in any of the ways possible in PHP, suggestingly:

```
include("/path/to/Database.php);
```

## Use

Assume:

- we are user with ID 3,
- our MySql or MariaDB database is named "mydatabase",
- our database is located at localhost, username "user" and password "password123",
- in our database "mydatabase" we have a table "items" with columns "item_id", "description", "created_at", "user_id".

Example to query database:

```
$userID = 3;

// Setup connection info
define("DB_HOST", "localhost");
define("DB_NAME_DEFAULT", "mydatabase");
define("DB_USERNAME", "root");
define("DB_PASSWORD", "password123");

// Create a dabtabase instance
$db = new Database(DB_HOST, DB_NAME_DEFAULT, DB_USERNAME, DB_PASSWORD);

// Execute a query
$items = $db->executeSQL("SELECT item_id, description, user_id FROM items WHERE user_id = ?", [$userID]);

// Print the result
print_r($items);
```

Example output:

```
Array
(
    [0] => Array
        (
            [item_id] => 2
            [description] => Apples
            [created_at] => 2023-10-22 20:40:47
            [user_id] => 3
        )

    [1] => Array
        (
            [item_id] => 3
            [description] => Bananas
            [created_at] => 2023-10-22 20:40:51
            [user_id] => 3
        )

    [...] => Array
        {
            [...] => ...
        }
}
```

## Public Interface

### Execute SQL query

- executeSQL($sql, $params = []): This method is the primary way to execute SQL queries using the class. It takes an SQL statement and optional parameters, ensures a connection is established, prepares and executes the statement, and handles any errors. It also returns results or status based on the type of SQL command (SELECT, INSERT, UPDATE, etc.).

### Query info

- getLastInsertID(): Returns the ID of the last inserted row or the value of an auto increment column. This is useful after performing an INSERT operation.

- getRowCount(): Returns the number of rows returned for a SELECT query. This can be useful for understanding the size of the result set returned by a query.

- getAffectedRows(): Provides the number of rows affected by the last DELETE, INSERT, or UPDATE SQL statement.

### Errors

- getErrorStack(): Get all errors collected by all instances of the database class.
