<?php
/**
 * PostgreSQL database driver
 *
 * MIT License
 *
 * Copyright (C) 2009-2017 by Nurlan Mukhanov <nurike@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace DBD;

/**
 * Class Pg
 *
 * @package DBD
 */
class Pg extends DBD
{
    /**
     * Do real connection. Can be invoked if OnDemand is set to TRUE
     *
     * @return void
     */
    public function _connect() {
        $this->dbh = pg_connect($this->dsn);

        if(!$this->dbh)
            trigger_error("Can not connect to PostgreSQL server! ", E_USER_ERROR);
    }

    /**
     * Replacement for constructor
     *
     * @return \DBD\PgExtend
     */
    public function connect() {
        $dsn = "host={$this->dsn} ";
        $dsn .= "dbname={$this->database} ";
        $dsn .= $this->username ? "user={$this->username} " : "";
        $dsn .= $this->password ? "password={$this->password} " : "";
        $dsn .= $this->port ? "port={$this->port} " : "";
        $dsn .= "options='--application_name=DBD-PHP' ";

        $this->dsn = $dsn;

        if($this->options['OnDemand'] == false) {
            $this->_connect();
        }

        return new PgExtend($this);
    }

    /**
     * returns the number of tuples (instances/records/rows) affected by INSERT, UPDATE, and DELETE queries.
     *
     * @return int
     */
    protected function _affectedRows() {
        return pg_affected_rows($this->result);
    }

    /**
     * Sends BEGIN; command
     *
     * @return resource
     */
    protected function _begin() {
        return $this->_query("BEGIN;");
    }

    /**
     * Send's COMMIT; command
     *
     * @return resource
     */
    protected function _commit() {
        return $this->_query("COMMIT;");
    }

    /**
     * Compiles INSERT query
     *
     * @param string $table
     * @param array  $params
     * @param string $return
     *
     * @return string
     */
    protected function _compileInsert($table, $params, $return = "") {
        return "INSERT INTO $table ({$params['COLUMNS']}) VALUES ({$params['VALUES']})" . ($return ? " RETURNING {$return}" : "");
    }

    /**
     * Compiles UPDATE query
     *
     * @param string $table
     * @param array  $params
     * @param string $where
     * @param string $return
     *
     * @return string
     */
    protected function _compileUpdate($table, $params, $where, $return = "") {
        return "UPDATE $table SET {$params['COLUMNS']}" . ($where ? " WHERE $where" : "") . ($return ? " RETURNING {$return}" : "");
    }

    /**
     * Convert integer, double and float values to corresponding PHP types. By default Postgres returns them as string
     *
     * @param $data
     * @param $type
     *
     * @return mixed
     */
    protected function _convertIntFloat(&$data, $type) {
        // TODO: in case of fetchrowset do not get each time and use static variable

        if($data && pg_num_fields($this->result) != count($data)) {

            $names = [];
            for($i = 0; $i < pg_num_fields($this->result); $i++) {
                $names[pg_field_name($this->result, $i)]++;
            }
            $names = array_filter($names, function($v) {
                return $v > 1;
            });

            $dublications = "";

            foreach($names as $key => $value) {
                $dublications .= "[<b>{$key}</b>] => <b style='color:crimson'>{$value}</b><br />";
            }

            trigger_error("Statement result has " . pg_num_fields($this->result) . " columns while fetched row only " . count($data) . ". 
				Fetching it associative reduces number of columns. 
				Rename column with `AS` inside statement or fetch as indexed array.<br /><br />
				Dublicating columns are:<br /> {$dublications}<br />", E_USER_ERROR);
        }

        $types = [];

        $map = [
            'int'       => 'integer',
            'int2'      => 'integer',
            'int4'      => 'integer',
            'int8'      => 'integer',
            'serial4'   => 'integer',
            'serial8'   => 'integer',
            'smallint'  => 'integer',
            'bigint'    => 'integer',
            'bigserial' => 'integer',
            'serial'    => 'integer',
            'numeric'   => 'float',
            'decimal'   => 'float',
            'real'      => 'float',
            'float'     => 'float',
            'float4'    => 'float',
            'float8'    => 'float'
        ];

        if($type == 'row') {
            if($data) {
                // count how many fields we have and get their types
                for($i = 0; $i < pg_num_fields($this->result); $i++) {
                    $types[] = pg_field_type($this->result, $i);
                }

                // Identify on which column we are
                $i = 0;
                //        row    idx      value
                foreach($data as $key => $value) {
                    // if type of current column exist in map array
                    if(array_key_exists($types[$i], $map)) {
                        // using data key, cause can be
                        //printf("Type: %s\n",$types[$i]);
                        $data[$key] = ($map[$types[$i]] == 'integer' ? intval($value) : floatval($value));
                    }
                    $i++;
                }
            }
        }

        return $data;
    }

    /**
     * Closes the non-persistent connection to a PostgreSQL database associated with the given connection resource
     *
     * @return bool
     */
    protected function _disconnect() {
        return pg_close($this->dbh);
    }

    /**
     * Returns the last error message for a given connection.
     *
     * @return string
     */
    protected function _errorMessage() {
        if ($this->dbh)
            return pg_last_error($this->dbh);
        else
            return pg_last_error();
    }

    /**
     * Escapes a string for querying the database.
     *
     * @param $string
     *
     * @return string
     */
    protected function _escape($string) {
        if(!isset($string) or $string === null) {
            return "NULL";
        }
        $str = pg_escape_string($string);

        return "'$str'";
    }

    /**
     * Returns an array that corresponds to the fetched row (record).
     *
     * @return array
     */
    protected function _fetchArray() {
        return pg_fetch_array($this->result, 0, PGSQL_NUM);
    }

    /**
     * Returns an associative array that corresponds to the fetched row (records).
     *
     * @return array
     */
    protected function _fetchAssoc() {
        return pg_fetch_assoc($this->result);
    }

    /**
     * Will return the number of rows in a PostgreSQL result resource.
     *
     * @return int
     */
    protected function _numRows() {
        return pg_affected_rows($this->result);
    }

    /**
     * Executes the query on the specified database connection.
     *
     * @param $statement
     *
     * @return resource
     */
    protected function _query($statement) {
        return @pg_query($this->dbh, $statement);
    }

    protected function _queryExplain($statement) {
        //TODO: return @pg_query($this->dbh, "EXPLAIN $statement");
    }

    /**
     * Sends ROLLBACK; command
     *
     * @return resource
     */
    protected function _rollback() {
        return $this->_query("ROLLBACK;");
    }
}

/**
 * Class PgExtend
 *
 * @package DBD
 */
final class PgExtend extends Pg implements DBI
{
    /**
     * PgExtend constructor.
     *
     * @param        $object
     * @param string $statement
     */
    public function __construct($object, $statement = "") {
        parent::extendMe($object, $statement);
    }
}