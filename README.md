SimplePDO Database Wrapper
==========

PDO variant of the [SimpleMySQLi class](https://github.com/bennettstone/simple-mysqli), designed to use prepared queries while providing support for existing implementations using SimpleMySQLi.

**This class is designed to return result sets as OBJECTS rather than arrays** (in keeping with the whole OOP structure), so it isn't technically fully backward compatible with existing SimpleMySQLi implementations, however, the swap is fairly straightfoward:

```php
//SimpleMySQLi get_row
list( $username ) = $db->get_row( "SELECT username FROM users WHERE user_id = 10 LIMIT 1" );
echo $username;

//SimplePDO get_row
$user = $db->get_row( "SELECT username FROM users WHERE user_id = 10 LIMIT 1" );
echo $user->username;
```

**Although this class is designed to support normal (non prepared) AND the more secure prepared statement queries, obviously using prepared statements is the purpose of this class (the PDO implementation is mainly because it 'could' be done).**  That being said, the above query for this class _should_ actually look like...

```php
$user = $db->get_row( "SELECT username FROM users WHERE user_id = ? LIMIT 1", array( 10 ) );
echo $user->username;
```

As of 29-Nov-2014, the "insert_multi()" function is **not** implemented in this class from SimpleMySQLi.

##Initialization

Same as simplemysqli, you can initiate this class with a new instance, or the singleton:

```php
require_once( 'SimplePDO.php' );

$params = array(
    'host' => 'localhost', 
    'user' => 'root', 
    'password' => 'root', 
    'database' => 'yourmagicdatabase'
);
//Set the options
SimplePDO::set_options( $params );

//Initiate the class as a new instance
$database = new SimplePDO();

//OR use the singleton...
$database = SimplePDO::getInstance();
```

##Available functions and usage

This class can:

- Connect to a given MySQL server using PDO
- Execute arbitrary SQL queries
- Retrieve the number of query result rows, result columns and last inserted table identifier
- Retrieve the query results in a single array
- Escape a single string or an array of literal text values to use in queries
- Determine if one value or an array of values contain common MySQL function calls
- Check of a table exists
- Check of a given table record exists
- Return a query result that has just one row
- Execute INSERT, UPDATE and DELETE queries from values that define tables, field names, field values and conditions
- Truncate a table
- Display the total number of queries performed during all instances of the class

###Straight query

```php
$clear_password = $database->query( "UPDATE users SET user_password = ? WHERE user_id = ?", array( 'NULL',  5 ) );
```

Retrieving Data
===============

###Get Results

```php
$all_users = $database->get_results( "SELECT user_name, user_email FROM users WHERE user_active = ?", array( 1 ) );
foreach( $all_users as $user )
{
    echo $user->user_name .' '. $user->user_email .'<br />';
}
```

###Get Results with LIKE statement

Using LIKE statements in prepared-statement-land requires that the actual array value be encapsulated with the percentage signs as follows...

```php
//CORRECT
$results = $database->get_results( "SELECT user_name, user_email FROM users WHERE user_name LIKE ? AND user_email = ? LIMIT 10", array( '%some%', 'you@magic.com' ) );
foreach( $results as $user )
{
    echo $user->user_name .' '. $user->user_email .'<br />';
}

//THIS WILL NOT WORK- DO NOT DO THIS...
$results = $database->get_results( "SELECT user_name, user_email FROM users WHERE user_name LIKE '%?%' AND user_email = ? LIMIT 10", array( 'some', 'you@magic.com' ) );
```

###Get Results using IN() statements

Unfortunately, to handle IN statements, some extra work is indeed required to handle parameter bindings for security [PHP.net](http://php.net/manual/en/pdostatement.execute.php), but it's not too bad, and in this case, requires only a single extra line of code.

```php
//List of user IDs to retrieve
$list = array( 1, 48, 51 );

//Map of prepared "?" statements to correspond
$prep_bindings = $database->prepare_in( $list );

//Run the query as usual
$in_list = $database->get_results( "SELECT user_name FROM users WHERE user_id IN($prep_bindings)", $list );
```

###Get single row

```php
$user = $database->get_row( "SELECT user_registered FROM users WHERE user_id = ?", array( 5 ) );
echo $user->user_registered;
```

###Get number of rows

```php
echo 'Total users: '. $database->num_rows( "SELECT COUNT(user_id) FROM users" );
```

Managing Data
===============

###Insert a record

```php
//Prepare the insertion array, keys must match column names
$userdata = array(
    'user_name' => 'some username', 
    'user_password' => 'somepassword (should be hashed)', 
    'user_email' => 'someone@email.com', 
    'user_registered' => 'NOW()', 
    'user_active' => 1
);

//Run the insertion
$insert = $database->insert( 'your_db_table', $userdata );

//Get the last inserted ID
echo 'Last user ID '. $insert;
```

###Update record(s)

```php
//Values to update
$update = array(
    'user_name' => 'New username', 
    'user_password' => 'new password (should still be hashed!)', 
    'user_last_login' => 'NULL'
);

//WHERE clauses
$where = array(
    'user_id' => 51
);

//Limit max updates
$limit = 1;

//Run the update, returns the number of affected rows
echo $database->update( 'your_db_table', $update, $where, $limit );
```

###Delete record(s)

```php
//The WHERE clauses
$delete_where = array(
  'user_id' => 47, 
  'user_active' => 0  
);

//Limit for deletions
$limit = 1;

//Run the query
$deleted = $database->delete( 'your_db_table', $delete_where, $limit );
```

Supplemental Functions
===================

###Get field names in a given table

Returns array

```php
$table_fields = $database->list_fields( 'your_db_table' );
echo '<pre>';
echo 'Fields in table: '. PHP_EOL;
print_r( $table_fields );
echo '</pre>';
```

###Get the number of fields in a table

Returns int

```php
$col_count = $database->num_fields( 'your_db_table' );
echo 'There are '. $col_count . ' fields in the table';
```

###Truncate database tables

Returns int representing number of tables truncated

```php
$tables = array(
    'table1', 
    'table2'
);
echo $database->truncate( $tables );
```

###Find out if a table exists

Returns bool, useful for automated actions such as making sure tables exist, and if they don't, running auto installers

```php
$table_exists = $database->table_exists( 'nonexistent' );
```

###Output number of total queries

```php
echo 'Total Queries: '. $database->total_queries();
```