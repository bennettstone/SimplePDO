<?php
/*------------------------------------------------------------------------------
** File:        SimplePDO.php
** Class:       SimplePDO
** Description: PHP PDO wrapper class to handle common database queries and operations 
** Version:     1.0
** Updated:     29-Nov-2014
** Author:      Bennett Stone
** Homepage:    www.phpdevtips.com 
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2015 BENNETT STONE
**
** The source code included in this package is free software; you can
** redistribute it and/or modify it under the terms of the GNU General Public
** License as published by the Free Software Foundation. This license can be
** read at:
**
** http://www.opensource.org/licenses/gpl-license.php
**
** This program is distributed in the hope that it will be useful, but WITHOUT 
** ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
** FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. 
**------------------------------------------------------------------------------ */
/*******************************
 Example initialization:
 
 require_once( 'SimplePDO.php' );

 $params = array(
     'host' => 'localhost', 
     'user' => 'root', 
     'password' => 'root', 
     'database' => 'yourmagicdatabase'
 );
 //Set the options
 SimplePDO::set_options( $params );

 //Initiate the class
 $database = new SimplePDO();

 //OR...
 $database = SimplePDO::getInstance();
 
 NOTE:
 All examples provided below assume that this class has been initiated
 Examples below assume the class has been iniated using $database = SimplePDO::getInstance();
********************************/
class SimplePDO {
    
    private $pdo = null;
    private $link = null;
    public $filter;
    static $inst = null;
    private $c_query;
    private $counter = 0;
    private $sql_constants = array(
        'NOW()', 
        'TIMESTAMP()', 
        'UNIX_TIMESTAMP()', 
        'NULL'
    );
    static $settings = array(
        'host' => '', 
        'user' => '', 
        'password' => '', 
        'database' => '', 
        'results' => 'object', 
        'charset' => 'utf8'
    );
    
    
    public function __construct()
    {
        $fetch_mode = ( self::$settings["results"] == 'object' ) ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC;
        $options = array(
            PDO::ATTR_DEFAULT_FETCH_MODE => $fetch_mode, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES ".self::$settings["charset"], 
            PDO::ATTR_EMULATE_PREPARES => false
        );
        $dsn = 'mysql:dbname='.self::$settings["database"].';host='.self::$settings["host"].';charset='.self::$settings["charset"];
        
        try {
            $this->pdo = new PDO( $dsn, self::$settings["user"], self::$settings["password"], $options );
            $this->link = true;
        } catch( PDOException $e ) {
            trigger_error( $e->getMessage() );
        }
    }
    //end __construct()
    
    
    /**
     * Sanitize user data
     * This is pretty unecessary, since the entire codebase of this class
     * uses PDO's prepared statements, and is left here JUST IN CASE as a fallback
     * for users switching from SimpleMySqli (like myself)
     *
     * Example usage:
     * $user_name = $database->filter( $_POST['user_name'] );
     * 
     * Or to filter an entire array:
     * $data = array( 'name' => $_POST['name'], 'email' => 'email@address.com' );
     * $data = $database->filter( $data );
     *
     * @access public
     * @param mixed $data
     * @return mixed $data
     */
     public function filter( $data )
     {
         if( !is_array( $data ) )
         {
             $data = $this->pdo->quote( $data );
             $data = trim( htmlentities( $data, ENT_QUOTES, 'UTF-8', false ) );
         }
         else
         {
             //Self call function to sanitize array data
             $data = array_map( array( $this, 'filter' ), $data );
         }
         return $data;
     }
     //end filter()
     
     
     /**
      * Extra function to filter when only basic sanitizing is needed
      * @access public
      * @param mixed $data
      * @return mixed $data
      */
     public function escape( $data )
     {
         if( !is_array( $data ) )
         {
             $data = $this->pdo->quote( $data );
         }
         else
         {
             //Self call function to sanitize array data
             $data = array_map( array( $this, 'escape' ), $data );
         }
         return $data;
     }
     //end escape()
    
    
    /**
     * Normalize sanitized data for display (reverse $database->filter cleaning)
     *
     * Example usage:
     * echo $database->clean( $data_from_database );
     *
     * @access public
     * @param string $data
     * @return string $data
     */
     public function clean( $data )
     {
         $data = stripslashes( $data );
         $data = html_entity_decode( $data, ENT_QUOTES, 'UTF-8' );
         $data = nl2br( $data );
         $data = urldecode( $data );
         return $data;
     }
     //end clean()
     
     
     /**
      * Function to prepare "?" values for binding params with 
      * mysql IN clauses.
      * See http://php.net/manual/en/pdostatement.execute.php for example
      *
      * Usage in actual query looks like:
      *
      * $list = array( 1, 48, 51 );
      * $matched = $db->prepare_in( $list );
      * $db->get_results( "SELECT user_name FROM users WHERE user_id IN($matched)", $list );
      *
      * @access public
      * @param array
      * @return string
      */
     public function prepare_in( $values = array() )
     {
         return implode( ',', array_fill( 0, count( $values ), '?' ) );
     }
     //end prepare_in()
    
    
    /**
     * Perform queries
     * All following functions run through this function
     *
     * @access public
     * @param string $query
     * @param array $bindings
     * @param bool $internal_call (used to differentiate between class calls and direct calls)
     * @return string
     * @return array
     * @return bool
     *
     */
    public function query( $query, $bindings = array(), $internal_call = false )
    {
        try {
            
            $this->counter++;
            $this->c_query = $this->pdo->prepare( $query );
            if( empty( $bindings ) )
            {
                $this->c_query->execute();
            }
            else
            {
                $this->c_query->execute( (array)$bindings );
            }
            
            //Alternate the response based on class internal vs. direct call to "query()"
            if( $internal_call === true )
            {
                return $this->c_query;   
            }
            elseif( $this->c_query && $this->lastid() )
            {
                return $this->lastid(); 
            }
            else
            {
                return false;
            }
            
        } catch( PDOException $e ) {
            
            //Handle the error with anything you like
            trigger_error( $e->getMessage() );
            
        }
    }
    //end query()
    
    
    /**
     * Perform query to retrieve array of associated results
     *
     * Example usage:
     * $users = $database->get_results( "SELECT name, email FROM users ORDER BY name ASC" );
     * foreach( $users as $user )
     * {
     *      echo $user->name . ': '. $user->email .'<br />';
     * }
     *
     * @access public
     * @param string
     * @return array
     *
     */
    public function get_results( $query, $bindings = array() )
    {
        $this->c_query = $this->query( $query, $bindings, true );
        if( $this->c_query )
        {
            return $this->c_query->fetchAll();   
        }
        else
        {
            return false;
        }
    }
    //end get_results()
    
    
    /**
     * Return specific row based on db query
     *
     * Example usage:
     * $user = $database->get_row( "SELECT name, email FROM users WHERE user_id = ? AND name LIKE ?", array( 44, '%bennett% ) );
     * echo $user->name . ' '. $user->email;
     *
     * @access public
     * @param string
     * @return array
     *
     */
    public function get_row( $query, $bindings = array() )
    {
        $this->c_query = $this->query( $query, $bindings, true );
        if( $this->c_query )
        {
            return $this->c_query->fetch();   
        }
        else
        {
            return false;
        }
    }
    //end get_row()
    
    
    /**
     * Count number of rows found matching a specific query
     *
     * Example usage:
     * $rows = $database->num_rows( "SELECT COUNT(id) FROM users WHERE user_id = ?", array( 10 ) );
     *
     * @access public
     * @param string
     * @return int
     *
     */
    public function num_rows( $query, $bindings = array() )
    {
        $this->c_query = $this->query( $query, $bindings, true );
        if( $this->c_query )
        {
            return $this->c_query->fetchColumn();   
        }
        else
        {
            return false;
        }
        
    }
    //end num_rows()
    
    
    /**
     * Insert data into database table
     *
     * Example usage:
     * $user_data = array(
     *      'name' => 'Bennett', 
     *      'email' => 'email@address.com', 
     *      'active' => 1, 
     *      'date' => 'NOW()'
     * );
     * $database->insert( 'users_table', $user_data );
     *
     * @access public
     * @param string table name
     * @param array table column => column value
     * @return int $lastid
     */
    public function insert( $table, $vars = array() )
    {
        //Make sure the array isn't empty
        if( empty( $vars ) )
        {
            return false;
        }
        
        $fields = array();
        $values = array();
        
        foreach( $vars as $field => $value )
        {
            $field = trim( $field );
            $fields[] = $field;
            
            //If we're dealing with a "NOW()" type statement, we must pass directly and remove from bound params
            if( in_array( $value, $this->sql_constants ) )
            {
                unset( $vars[$field] );
                $values[] = $this->unquote( $value );
            }
            else
            {
                $values[] = ':'.$field;   
            }
        }
        
        $fields = ' (' . implode(', ', $fields) . ')';
        $values = '('. implode(', ', $values) .')';
        
        $sql = "INSERT INTO ".$table;
        $sql .= $fields .' VALUES '. $values;
        
        $this->c_query = $this->query( $sql, $vars, true );
        if( $this->c_query )
        {
            return $this->lastid();   
        }
        else
        {
            return false;
        }
    }
    //end insert()
    
    
    /**
     * Update data in database table
     *
     * Example usage:
     * $update = array( 'name' => 'Not bennett', 'email' => 'someotheremail@email.com' );
     * $update_where = array( 'user_id' => 44, 'name' => 'Bennett' );
     * $database->update( 'users_table', $update, $update_where, 1 );
     *
     * @access public
     * @param string table name
     * @param array values to update table column => column value
     * @param array where parameters table column => column value
     * @param int limit
     * @return int affected rows
     *
     */
    public function update( $table, $variables = array(), $where = array(), $limit = '' )
    {
        //Make sure the required data is passed before continuing
        //This does not include the $where variable as (though infrequently)
        //queries are designated to update entire tables
        if( empty( $variables ) )
        {
            return false;
        }
        
        $sql = "UPDATE ". $table ." SET ";
        
        $updates = array();
        $clauses = array();
        foreach( $variables as $field => $value )
        {
            $field = trim( $field );

            //If we're dealing with a "NOW()" type statement, we must pass directly and remove from bound params
            if( in_array( $value, $this->sql_constants ) )
            {
                unset( $variables[$field] );
                $updates[] = "`".$field ."` = ". $this->unquote( $value );
            }
            else
            {
                $updates[] = "`".$field .'` = ?';   
            }
        }

        $sql .= implode(', ', $updates);
        
        //Add the $where clauses as needed
        if( !empty( $where ) )
        {
            foreach( $where as $field => $value )
            {
                $field = trim( $field );

                //If we're dealing with a "NOW()" type statement, we must pass directly and remove from bound params
                if( in_array( $value, $this->sql_constants ) )
                {
                    unset( $vars[$field] );
                    $clauses[] = "`".$field ."` = ". $this->unquote( $value );
                }
                else
                {
                    $clauses[] = "`".$field .'` = ?';   
                }
                
            }
            $sql .= ' WHERE '. implode( ' AND ', $clauses );   
        }
        
        if( !empty( $limit ) )
        {
            $sql .= ' LIMIT '. (int)$limit;
        }
        
        //Merge the arrays to bind to params in query()
        $vars = array_merge( array_values( $variables ), array_values( $where ) );
        
        $this->c_query = $this->query( $sql, $vars, true );
        if( $this->c_query )
        {
            return $this->c_query->rowCount();
        }
        else
        {
            return false;
        }
    }
    //end update()
    
    
    /**
     * Delete data from table
     *
     * Example usage:
     * $where = array( 'user_id' => 44, 'email' => 'someotheremail@email.com' );
     * $database->delete( 'users_table', $where, 1 );
     *
     * @access public
     * @param string table name
     * @param array where parameters table column => column value
     * @param int max number of rows to remove.
     * @return int affected rows
     */
    public function delete( $table, $where = array(), $limit = '' )
    {
        //Delete clauses require a where param, otherwise use "truncate"
        if( empty( $where ) )
        {
            return false;
        }

        $sql = "DELETE FROM ". $table;
        foreach( $where as $field => $value )
        {
            $field = trim( $field );

            //If we're dealing with a "NOW()" type statement, we must pass directly and remove from bound params
            if( in_array( $value, $this->sql_constants ) )
            {
                unset( $where[$field] );
                $clauses[] = "`".$field ."` = ". $this->unquote( $value );
            }
            else
            {
                $clauses[] = "`".$field .'` = ?';   
            }

        }
        $sql .= ' WHERE '. implode( ' AND ', $clauses );

        if( !empty( $limit ) )
        {
            $sql .= " LIMIT ". $limit;
        }
        
        //Params
        $vars = array_values( $where );
        
        $this->c_query = $this->query( $sql, $vars, true );
        if( $this->c_query )
        {
            return $this->c_query->rowCount();
        }
        else
        {
            return false;
        }
    }
    //end delete()
    
    
    /**
     * Get last auto-incrementing ID associated with an insertion
     *
     * Example usage:
     * $database->insert( 'users_table', $user );
     * $last = $database->lastid();
     *
     * OR:
     * echo $database->insert( 'users_table', $user );
     *
     * @access public
     * @param none
     * @return int last insert id
     */
    public function lastid()
    {
        return $this->pdo->lastInsertId();
    }
    //end lastid()
    
    
    /**
     * Determine if database table exists
     * Example usage:
     * if( !$database->table_exists( 'checkingfortable' ) )
     * {
     *      //Install your table or throw error
     * }
     *
     * @access public
     * @param string
     * @return bool
     *
     */
     public function table_exists( $name )
     {
         $this->c_query = $this->query( "SHOW TABLES LIKE '$name'", array(), true );
         if( $this->c_query && $this->c_query->rowCount() > 0 )
         {
             return true;
         }
         else
         {
             return false;
         }
     }
     //end table_exists()
     
     
     /**
      * Get number of fields
      *
      * Example usage:
      * echo $database->num_fields( "users_table" );
      *
      * @access public
      * @param query
      * @return int
      */
     public function num_fields( $table )
     {
         return count( $this->list_fields( $table ) );
     }
     //end num_fields()


     /**
      * Get field names associated with a table
      *
      * Example usage:
      * $fields = $database->list_fields( "users_table" );
      * echo '<pre>';
      * print_r( $fields );
      * echo '</pre>';
      *
      * @access public
      * @param string $table
      * @return array
      */
     public function list_fields( $table )
     {
         $this->c_query = $this->query( "DESCRIBE $table", array(), true );
         if( $this->c_query )
         {
             return $this->c_query->fetchAll( PDO::FETCH_COLUMN );
         }
         else
         {
             return false;
         }
     }
     //end list_fields()
     
     
     /**
      * Truncate entire tables
      *
      * Example usage:
      * $remove_tables = array( 'users_table', 'user_data' );
      * echo $database->truncate( $remove_tables );
      *
      * @access public
      * @param array database table names
      * @return int number of tables truncated
      *
      */
     public function truncate( $tables = array() )
     {
         if( !empty( $tables ) )
         {
             $truncated = 0;
             foreach( $tables as $table )
             {
                 $this->c_query = $this->query( "TRUNCATE TABLE `".trim($table)."`", array(), true );
                  if( $this->c_query )
                  {
                      $truncated++;
                  }
             }
             return $truncated;
         }
     }
     //end truncate()
    
    
    /**
     * Return the number of rows affected by a given query
     * 
     * Example usage:
     * $database->insert( 'users_table', $user );
     * $database->affected();
     *
     * @access public
     * @param none
     * @return int
     */
    public function affected()
    {
        return $this->pdo->rowCount();
    }
    //end affected()
    
    
    /**
     * Output the total number of queries
     * Generally designed to be used at the bottom of a page after
     * scripts have been run and initialized as needed
     *
     * Example usage:
     * echo 'There were '. $database->total_queries() . ' performed';
     *
     * @access public
     * @param none
     * @return int
     */
    public function total_queries()
    {
        return $this->counter;
    }
    //end total_queries()
    
    
    /**
     * Function to support unquote() to unwrap
     * values associated with mysql commands such as NOW()
     * or NULL
     * @access private
     * @param sting $value
     * @return string $value
     */
    private function add_wrap( $value )
    {
        return "'".$value."'";
    }
    //end add_wrap()
    
    
    /**
     * Function to remove quotes or other encapsulators from
     * mysql comands found in $this->sql_constants
     * This allows mysql commands to be passed directly into
     * queries from array params so they may be executed
     * Turns $db->update( 'users', array( 'timestamp' => 'NOW()', 'name' => 'Someone' ), array( 'user_id' => 1 ) );
     * into:
     * "UPDATE users SET timestamp = NOW(), name = ? WHERE user_id = ?"
     * and appropriately binds the parameters that ARE NOT in the sql_constants array
     * @access private
     * @param array $value
     * @return $array
     */
    private function unquote( $value )
    {
        $mapped = array_map( array( $this, 'add_wrap' ), $this->sql_constants );
        return str_replace( $mapped, $this->sql_constants, $value );
    }
    //end unquote()
    
    
    /**
     * Singleton function
     *
     * Example usage:
     * $database = SimplePDO::getInstance();
     *
     * @access private
     * @return self
     */
    static function getInstance()
    {
        if( self::$inst == null )
        {
            self::$inst = new SimplePDO();
        }
        return self::$inst;
    }
    //end getInstance()
    
    
    /**
     * Static function to set database constants and carry through
     * into the static singleton function access layer
     *
     * $params = array(
     *    'host' => 'localhost', 
     *    'user' => 'yourdbusername', 
     *    'password' => 'yourdbpassword', 
     *    'database' => 'yourdatabase'
     * );
     * SimplePDO::set_options( $params );
     *
     * @access public
     * @param array
     * @return none
     */
    static function set_options( $array = array() )
    {
        if( !empty( $array ) )
        {
            foreach( $array as $k => $v )
            {
                if( isset( self::$settings[$k] ) )
                {
                    self::$settings[$k] = $v;
                }
            }
        }
    }
    //end set_options()

    
    /**
     * Close the connection at script destruction
     * @access public
     * @param none
     * @return none
     */
    public function __destruct()
    {
        if( $this->link )
        {
            $this->pdo = null;
        }
    }
    //end __destruct()
    
}
//end SimplePDO