<?php
/**
 * MIT License
 * ===========
 *
 * Copyright (c) 2012 Wings
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

// Current namespace
namespace Wings;

// Needed exception handlers
use Wings\Exceptions\ExecutionException;
use Wings\Exceptions\FetchException;
use Wings\Exceptions\FlyingException;

/**
 * Wings ORM
 *
 * @package     Wings
 * @author      Miranda <miranda@lunnaly.com>
 * @since       2012 December, 19.
 * @license     http://www.opensource.org/licenses/mit-license.php MIT License
 * @link        http://github.com/over9k/Wings
 * @version     0.5
 */
class ORM extends \PDO {
    /**
     * Current Wings version
     *
     * @var string
     */
    const VERSION = '0.5';

    /**
     * Current version ID
     *
     * @var int
     */
    const VERSION_ID = 05000;

    /**
     * Fetch modes for developer-friendly recognition
     *
     * @var int
     */
    const FETCH_OBJECT = 5;
    const FETCH_ASSOC = 2;
    const FETCH_ARRAY = 3;
    const FETCH_LAZY = 1;
    const FETCH_BOTH = 0;

    /**
     * Singleton holder for this class
     *
     * @var object
     */
    private static $instance = null;

    /**
     * Specified DSN to makes the connection
     *
     * @var string
     */
    private static $dsn = null;

    /**
     * Database username and password to authenticate
     *
     * @var string
     */
    private static $username, $password = null;

    /**
     * Used as the master variable holding the \PDO class
     *
     * @var object
     */
    private $stmt = null;

    /**
     * Current flying table
     *
     * @var string
     */
    private $table = null;

    /**
     * Used as a temporary variable to hold queries
     *
     * @var string
     */
    private $scope = null;

    /**
     * Default quotes to use on query hydratation
     */
    private $quotes = null;

    /**
     * Is the previous query a SELECT statement?
     *
     * @var bool
     */
    private $isSelect = false;

    /**
     * Variable to checks if is the current instance flying
     *
     * @var bool
     */
    public $flying = false;

    /**
     * Count of executed queries till now.
     *
     * @var int
     */
    public $numQueries = 0;

    /**
     * Configures the DSN to connect
     *
     * @access  public
     * @param   string $dsn DSN string to use on the connection
     * @return  void
     */
    public static function configure($dsn) {
        static::$dsn = (string) $dsn;
    }

    /**
     * Saves the database username to use on the connection
     *
     * @access  public
     * @param   string $username Database username
     * @param   string $password Database password
     * @return  void
     */
    public static function authenticate($username, $password) {
        static::$username = (string) $username;
        static::$password = (string) $password;
    }

    /**
     * Static constructor
     *
     * @access  public
     * @param   string $table Table to fly! :)
     * @return  object
     */
    public static function fly($table) {
        if (!is_object(static::$instance)) {
            static::$instance = new static($table);
        }

        return static::$instance;
    }

    /**
     * Class constructor.
     *
     * @access  public
     * @param   string $table Table name to use as reference for future queries
     * @return  object
     */
    public function __construct($table) {
        try {
            $this->stmt = new parent(static::$dsn, static::$username, static::$password,
                                array(
                                    parent::ATTR_PERSISTENT => true,
                                    parent::ATTR_AUTOCOMMIT => false,
                                    parent::ATTR_ERRMODE    => parent::ERRMODE_EXCEPTION
                                )
                               );

            // Did we succeed on attempting to fly? :)
            if ($this->stmt && $this->stmt instanceof \PDO) {
                // We now can fly without any worries :)
                $this->flying = true;

                // We're flying under which table?
                $this->table = (string) $table;

                // What is the loaded quoting style?
                $this->quotes = $this->get_quote_style();
            }
        } catch(ExecutionException $e) {
            throw new ExecutionException('Error while attempting to fly. Details: ' . $e->getMessage());
        }
    }

    /**
     * Class destructor$string)
     *
     * @access  public
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Returns a PDO constant for the defined param by using the $value as reference
     *
     * @access private
     * @param   mixed $value Value to define which params is
     * @return  mixed Constant name of the defined param or false if has no match
     */
    private static function defineType($value) {
        switch($value) {
            case (is_int($value)):
                $type = parent::PARAM_INT;
                break;
            case (is_string($value)):
                $type = parent::PARAM_STR;
                break;
            case (is_bool($value)):
                $type = parent::PARAM_BOOL;
                break;
            case (is_null($value)):
                $type = parent::PARAM_NULL;
                break;
            default:
                $type = false;
        }

        return $type;
    }

    /**
     * Prepares the given $data to bind
     *
     * @access  private
     * @return  array Binded array like: array('fields' => '`field1`, `field2`, `field3`', 'placeholders' => ':field1, :field2, :field3')
     */
    private static function insertPrepare(array $data) {
        ksort($data);
        $insert = array(
                        'fields' => rtrim(implode($this->quotes . ', ' . $this->quotes, array_keys($data)), ', ' . $this->quotes),
                        'placeholders' => $this->hydrate(':' . rtrim(implode(', :', array_keys($data)), ', :'))
                       );

        return $insert;
    }

    /**
     * Prepares the given $data to bind
     *
     * @access  private
     * @param   array $data Data to be prepared for binding
     * @return  string Binded string
     */
    private static function updatePrepare(array $data) {
        ksort($data);
        $update = null;

        foreach ($data as $key => $val) {
            $update .= $this->quotes . $key . $this->quotes . " = :$key, ";
        }

        return rtrim($update, ', ');
    }

    /**
     * Prepares the given $fields make it usable on SELECT statements
     *
     * @access  private
     * @param   array $fields Fields to prepare
     * @return  string Prepared fields string (like: `field1`, `field2`, `field3`)
     */
    private function selectPrepare(array $fields) {
        // Fields to select
        $select = null;

        foreach ($fields as $field) {
            $select .= $this->quotes . $field . $this->quotes . ', ';
        }

        return rtrim($select, ', ');
    }

    /**
     * Hydrates a value to make it usable in our queries
     *
     * @access  private
     * @param   string $string String to hydrate
     * @return  string Hydrated string
     */
    private function hydrate($string) {
        return trim(stripslashes($string));
    }

    /**
     * Returns the quoting style for the loaded driver
     * -- COPIED FROM IDIORM --
     *
     * @access  public
     * @param   string $string Value to hydrate
     * @return  string Hydrated string
     *
     * @see     https://github.com/j4mie/idiorm/
     */
    private function get_quote_style() {
        switch($this->stmt->getAttribute(parent::ATTR_DRIVER_NAME)) {
                case 'pgsql':
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                case 'sybase':
                    return '"';
                case 'mysql':
                case 'sqlite':
                case 'sqlite2':
                default:
                    return '`';
            }
    }

    /**
     * Executes a database query
     *
     * @access  public
     * @param   string $sql SQL Command to execute
     * @return  mixed May return the query results if everything is ok and the the param $return is equals to false, otherwise will return true
     */
    public function query($sql, $return = false) {
        try {
            $this->scope = null;
            $this->numQueries++;

            $sql = $this->hydrate($sql);
            $result = $this->stmt->query($sql);

            if ($result && $return) {
                return true;
            } elseif (!$result && $return) {
                return false;
            }

            return $result;
        } catch (FlyingException $e) {
            throw new FlyingException('Error while executing a database query in table [' . $this->table . ']. Details: ' . $e->getMessage());
        }
    }

    /**
     * Inserts a record to the flying table
     *
     * @access  public
     * @param   array $data Array with the parameters to insert. Must an array with this structure: array('field1' => 'value1')
     * @return  bool True if succeed, otherwise will return false
     */
    public function insert(array $data) {
        try {
            $insertData = static::insertPrepare($data);
            $this->scope = $this->stmt->prepare('INSERT INTO ' . $this->quotes . $this->table . $this->quotes . " ({$insertData['fields']}) VALUES ({$insertData['placeholders']})");

            foreach ($data as $key => $val) {
                $type = static::defineType($val);
                $this->scope->bindParam(':' . $key, $this->hydrate($val), $type);
            }

            if ($this->scope->execute()) {
                $this->numQueries++;

                return true;
            }

            return false;
        } catch(FlyingException $e) {
            throw new FlyingException('Error while inserting a row in table [' . $this->table . ']. Details: ' . $e->getMessage());
        }
    }

    /**
     * Updates a record from the flying table
     *
     * @access  public
     * @param   array $data Array with the values to be inserted. Usage: array('field1' => 'value1')
     * @param   string $where Where condition
     * @return  bool True if everything goes fine, otherwise will return false
     */
    public function update(array $data, $where = null) {
        try {
            $updateData = static::updatePrepare($data);
            $where = (isset($where) && !empty($where)) ? $this->hydrate($where) : 1;
            $this->scope = $this->stmt->prepare('UPDATE ' . $this->quotes . $this->table . $this->quotes . ' SET ' . $updateData . ' WHERE ' . $where);

            foreach ($data as $key => $val) {
                $type = static::defineType($val);
                $this->scope->bindParam(':' . $key, $this->hydrate($val), $type);
            }


            if ($this->scope->execute()) {
                $this->numQueries++;

                return true;
            }

            return false;
        } catch(FlyingException $e) {
            throw new FlyingException('Error while updating a row in table [' . $this->table . ']. Details: ' . $e->getMessage());
        }
    }

    /**
     * Deletes a record from the flying table
     *
     * @access  public
     * @param   string $where Where condition
     * @return  mixed Number of affected rows if has affected any rows, otherwise will return false
     */
    public function delete($where) {
        try {
            $where = (isset($where) && !empty($where)) ? $this->hydrate($where) : 1;

            $this->stmt->beginTransaction();
            $rows = $this->stmt->exec('DELETE FROM ' . $this->quotes . $this->table . $this->quotes . ' WHERE ' . $where);
            $this->stmt->commit();

            if (count($rows) > 0) {
                $this->numQueries++;

                return $rows;
            }

            return false;
        } catch(FlyingException $e) {
            throw new FlyingException('Error while deleting a row in table [' . $this->table . ']. Details: ' . $e->getMessage());
        }
    }

    /**
     * Select the given $fields from the flying table and return this class object
     *
     * @access  public
     * @param   array $fields Fields to fetch from the flying table
     * @param   string $where Where condition
     * @param   int $limit Selection limit
     * @param   string $order_by ORDER BY ?
     * @param   string $sorting_mode Sorting mode to select from the flying table
     * @param   bool $pdo_return Do you need the default PDOStatement return on SELECT queries?
     * @return  mixed May return this class object if everything fine, otherwise will return false
     */
    public function select(array $fields, $where = null, $limit = null, $order_by = null, $pdo_return = false) {
        // Redefine vars
        $fields = (isset($fields) && !empty($fields)) ? static::selectPrepare($fields) : '*';
        $where = (isset($where) && !empty($where)) ? ' WHERE (' . (string) $this->hydrate($where) . ')' : null;
        $limit = (isset($limit) && !empty($limit)) ? ' LIMIT 0,' . (int) $limit : null;
        $order_by = (isset($order_by) && !empty($order_by)) ? ' ORDER BY ' . (string) $order_by : null;

        $query = rtrim('SELECT ' . $fields . ' FROM ' . $this->quotes . $this->table . $this->quotes . $where . $limit . $order_by);
        $this->scope = $this->stmt->prepare($query);

        if ($this->scope->execute()) {
            // This is a SELECT statement
            $this->isSelect = true;

            // Increase the query counter
            $this->numQueries++;

            // Does the user need the PDOStatement return?
            if ($pdo_return) {
                return $this->scope;
            }

            return $this;
        }

        return false;
    }

    /**
     * Fetches the previous SELECT statement
     *
     * @access  public
     * @param   constant $mode The fetch mode in which the data recieved will be stored
     * @return  mixed Null when conditions are not met, stdClass(object) or string when conditions are met.
     */
    public function fetch($mode = ORM::FETCH_ASSOC) {
        try {
            if (!$this->flying || !$this->isSelect) {
                return false;
            }

            return $this->scope->fetch($mode);
        } catch (FetchException $e) {
            throw new FlyingException('Error while fetching a row from the table [' . $this->table . ']. Details: ' . $e->getMessage());
        }
    }

    /**
     * Rollback an user-caused mistake and return a boolean result
     *
     * @access  public
     * @return  bool True if succeed, otherwise will return false
     */
    public function rollback() {
        if ($this->flying && $this->stmt->rollBack()) {
            return true;
        }

        return false;
    }

    /**
     * Returns the number of executed queries along the script execution
     *
     * @access  public
     * @return  mixed If everything is ok, returns the number of executed queries, otherwise will return false
     */
    public function queries() {
        if ($this->flying) {
            return (int) $this->numQueries;
        }

        return false;
    }

    /**
     * Returns the number of affected rows by the previous command
     *
     * @access  public
     * @return  mixed If everything is ok, returns the number of affected rows, otherwise will return false
     */
    public function count() {
        if ($this->flying) {
            return $this->scope->rowCount();
        }

        return false;
    }

    /**
     * @access  public
     * @return  mixed May return the previous insert ID if everything is ok, otherwise will return false
     */
    public function lastId() {
        if ($this->flying) {
            return $this->stmt->lastInsertId();
        }

        return false;
    }

    /**
     * Truncates the flying table :)
     * NOTE: This will erase ALL your data. Be careful!
     *
     * @access  public
     * @return  bool True if succeed, otherwise will return false
     */
    public function truncate() {
        if ($this->flying) {
            return $this->query('TRUNCATE TABLE ' . $this->quotes . $this->table . $this->quotes, true);
        }

        return false;
    }

    /**
     * Returns the params for table dumping
     *
     * @access  public
     * @return  mixed If everything is ok, returns the dump data, otherwise will return false
     */
    public function debug()  {
        if ($this->flying) {
            return $this->scope->debugDumpParams();
        }

        return false;
    }

    /**
     * Closes the current connection
     *
     * @access  public
     * @return  void
     */
    public function close() {
        $this->stmt = null;
        $this->scope = null;
        $this->table = null;
        $this->isSelect = false;
        $this->numQueries = 0;
    }
}
?>
