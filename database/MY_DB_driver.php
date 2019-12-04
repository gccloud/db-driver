<?php

/**
 * @inheritDoc
 */
class MY_DB_driver extends CI_DB_driver
{
    /**
     * @inheritDoc
     */
    public function version()
    {
        if (isset($this->data_cache['version']))
        {
            return $this->data_cache['version'];
        }

        if (FALSE === ($sql = $this->_version()))
        {
            return ($this->db_debug) ? $this->display_error('db_unsupported_function') : FALSE;
        }

        $query = $this->query($sql)->row();
        return $this->data_cache['version'] = $query->ver;
    }

    /**
     * @inheritDoc
     */
    public function count_all($table = '')
    {
        if ($table === '')
        {
            return 0;
        }

        $query = $this->query($this->_count_string.$this->escape_identifiers('numrows').' FROM '.$this->protect_identifiers($table, TRUE, NULL, FALSE));
        if ($query->num_rows() === 0)
        {
            return 0;
        }

        $query = $query->row();
        $this->_reset_select();
        return (int) $query->numrows;
    }

    /**
     * @inheritDoc
     */
    public function list_tables($constrain_by_prefix = FALSE)
    {
        // Is there a cached result?
        if (isset($this->data_cache['table_names']))
        {
            return $this->data_cache['table_names'];
        }

        if (FALSE === ($sql = $this->_list_tables($constrain_by_prefix)))
        {
            return ($this->db_debug) ? $this->display_error('db_unsupported_function') : FALSE;
        }

        $this->data_cache['table_names'] = array();
        $query = $this->query($sql);

        foreach ($query->result_array() as $row)
        {
            // Do we know from which column to get the table name?
            if ( ! isset($key))
            {
                if (isset($row['table_name']))
                {
                    $key = 'table_name';
                }
                elseif (isset($row['TABLE_NAME']))
                {
                    $key = 'TABLE_NAME';
                }
                else
                {
                    /* We have no other choice but to just get the first element's key.
                     * Due to array_shift() accepting its argument by reference, if
                     * E_STRICT is on, this would trigger a warning. So we'll have to
                     * assign it first.
                     */
                    $key = array_keys($row);
                    $key = array_shift($key);
                }
            }

            $this->data_cache['table_names'][] = $row[$key];
        }

        return $this->data_cache['table_names'];
    }

    /**
     * @inheritDoc
     */
    public function list_fields($table)
    {
        if (FALSE === ($sql = $this->_list_columns($table)))
        {
            return ($this->db_debug) ? $this->display_error('db_unsupported_function') : FALSE;
        }

        $query = $this->query($sql);
        $fields = array();

        foreach ($query->result_array() as $row)
        {
            // Do we know from where to get the column's name?
            if ( ! isset($key))
            {
                if (isset($row['column_name']))
                {
                    $key = 'column_name';
                }
                elseif (isset($row['COLUMN_NAME']))
                {
                    $key = 'COLUMN_NAME';
                }
                else
                {
                    // We have no other choice but to just get the first element's key.
                    $key = key($row);
                }
            }

            $fields[] = $row[$key];
        }

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function field_data($table)
    {
        $query = $this->query($this->_field_data($this->protect_identifiers($table, TRUE, NULL, FALSE)));
        return ($query) ? $query->field_data() : FALSE;
    }

    /**
     * @inheritDoc
     */
    public function query($sql, $binds = FALSE, $return_object = NULL)
    {
        if ($sql === '')
        {
            log_message('error', 'Invalid query: '.$sql);
            return ($this->db_debug) ? $this->display_error('db_invalid_query') : FALSE;
        }
        elseif ( ! is_bool($return_object))
        {
            $return_object = ! $this->is_write_type($sql);
        }

        // Verify table prefix and replace if necessary
        if ($this->dbprefix !== '' && $this->swap_pre !== '' && $this->dbprefix !== $this->swap_pre)
        {
            $sql = preg_replace('/(\W)'.$this->swap_pre.'(\S+?)/', '\\1'.$this->dbprefix.'\\2', $sql);
        }

        // Compile binds if needed
        if ($binds !== FALSE)
        {
            $sql = $this->compile_binds($sql, $binds);
        }

        // Is query caching enabled? If the query is a "read type"
        // we will load the caching class and return the previously
        // cached query if it exists
        if ($this->cache_on === TRUE && $return_object === TRUE && $this->_cache_init())
        {
            $this->load_rdriver();
            if (FALSE !== ($cache = $this->CACHE->read($sql)))
            {
                return $cache;
            }
        }

        // Save the query for debugging
        if ($this->save_queries === TRUE)
        {
            $this->queries[] = $sql;
        }

        // Start the Query Timer
        $time_start = microtime(TRUE);

        // Run the Query
        if (FALSE === ($this->result_id = $this->simple_query($sql)))
        {
            if ($this->save_queries === TRUE)
            {
                $this->query_times[] = 0;
            }

            // This will trigger a rollback if transactions are being used
            if ($this->_trans_depth !== 0)
            {
                $this->_trans_status = FALSE;
            }

            // Grab the error now, as we might run some additional queries before displaying the error
            $error = $this->error();

            // Log errors
            log_message('error', 'Query error: '.$error['message'].' - Invalid query: '.$sql);

            if ($this->db_debug)
            {
                // We call this function in order to roll-back queries
                // if transactions are enabled. If we don't call this here
                // the error message will trigger an exit, causing the
                // transactions to remain in limbo.
                while ($this->_trans_depth !== 0)
                {
                    $trans_depth = $this->_trans_depth;
                    $this->trans_complete();
                    if ($trans_depth === $this->_trans_depth)
                    {
                        log_message('error', 'Database: Failure during an automated transaction commit/rollback!');
                        break;
                    }
                }

                // Display errors
                return $this->display_error(array('Error Number: '.$error['code'], $error['message'], $sql));
            }

            return FALSE;
        }

        // Stop and aggregate the query time results
        $time_end = microtime(TRUE);
        $this->benchmark += $time_end - $time_start;

        if ($this->save_queries === TRUE)
        {
            $this->query_times[] = $time_end - $time_start;
        }

        // Increment the query counter
        $this->query_count++;

        // Will we have a result object instantiated? If not - we'll simply return TRUE
        if ($return_object !== TRUE)
        {
            // If caching is enabled we'll auto-cleanup any existing files related to this particular URI
            if ($this->cache_on === TRUE && $this->cache_autodel === TRUE && $this->_cache_init())
            {
                $this->CACHE->delete();
            }

            return TRUE;
        }

        // Load and instantiate the result driver
        $driver		= $this->load_rdriver();
        $RES		= new $driver($this);

        // Is query caching enabled? If so, we'll serialize the
        // result object and save it to a cache file.
        if ($this->cache_on === TRUE && $this->_cache_init())
        {
            // We'll create a new instance of the result object
            // only without the platform specific driver since
            // we can't use it with cached data (the query result
            // resource ID won't be any good once we've cached the
            // result object, so we'll have to compile the data
            // and save it)
            $CR = new CI_DB_result($this);
            $CR->result_object	= $RES->result_object();
            $CR->result_array	= $RES->result_array();
            $CR->num_rows		= $RES->num_rows();

            // Reset these since cached objects can not utilize resource IDs.
            $CR->conn_id		= NULL;
            $CR->result_id		= NULL;

            $this->CACHE->write($sql, $CR);
        }

        return $RES;
    }

    /**
     * @inheritDoc
     */
    public function load_rdriver()
    {
        $db_driver_path =  APPPATH.'third_party/db-driver/';

        // If base class doesn't exist we load them
        if (!class_exists('CI_DB_result', false)) {
            require_once(BASEPATH.'database/DB_result.php');
        }

        if (!class_exists('MY_DB_result', false)) {
            require_once($db_driver_path.'/database/MY_DB_result.php');
        }

        $custom_driver = 'MY_DB_'.$this->dbdriver.'_result';
        $result_pathname = $db_driver_path.'database/drivers/'.$this->dbdriver.'/MY_'.$this->dbdriver.'_result.php';
        
        // If a custom DB_**_result for this driver exists and isn't already loaded we load it and return his name
        if (!class_exists($custom_driver, false) && file_exists($result_pathname)) {
            require_once($result_pathname);
            return $custom_driver;
        }

        // Here is CI basic operation to load default driver
        $driver = 'CI_DB_'.$this->dbdriver.'_result';

        if (!class_exists($driver, false))
        {
            require_once(BASEPATH.'database/drivers/'.$this->dbdriver.'/'.$this->dbdriver.'_result.php');
        }

        return $driver;
    }
    
    
}