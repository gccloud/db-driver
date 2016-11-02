<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Database Query Builder Class
 *
 * @class       MY_DB_query_builder
 * @package     CodeIgniter
 * @category    Core
 * @author      Gregory CARRODANO <g.carrodano@gmail.com>
 * @version     20161101
 */
abstract class MY_DB_query_builder extends MY_DB_driver
{
    /**
     * "Count All Results" query
     * @param  string
     * @param  bool
     * @return int
     */
    public function count_all_results($table = '', $reset = true)
    {
        if ($table !== '') {
            $this->_track_aliases($table);
            $this->from($table);
        }

        // ORDER BY usage is often problematic here (most notably on Microsoft SQL Server) and ultimately unnecessary for selecting COUNT(*) ...
        if ( ! empty($this->qb_orderby)) {
            $orderby = $this->qb_orderby;
            $this->qb_orderby = null;
        }

        $result = ($this->qb_distinct === true) ? $this->query($this->_count_string.$this->protect_identifiers('numrows')."\nFROM (\n".$this->_compile_select()."\n) CI_count_all_results") : $this->query($this->_compile_select($this->_count_string.$this->protect_identifiers('numrows')));

        if ($reset === true) {
            $this->_reset_select();
        } elseif ( ! isset($this->qb_orderby)) {
            // If we've previously reset the qb_orderby values, get them back
            $this->qb_orderby = $orderby;
        }

        if ($result->num_rows() === 0) {
            return 0;
        }

        $row = $result->row();
        return (int) $row->numrows;
    }

    /**
     * Get INSERT IGNORE query string
     * @param  string
     * @param  bool
     * @return string
     */
    public function get_compiled_insert_ignore($table = '', $reset = true)
    {
        if ($this->_validate_insert($table) === false) {
            return false;
        }

        $sql = $this->_insert_ignore(
            $this->protect_identifiers( $this->qb_from[0], true, null, false),
            array_keys($this->qb_set),
            array_values($this->qb_set)
        );

        if ($reset === true) {
            $this->_reset_write();
        }

        return $sql;
    }

    /**
     * INSERT IGNORE
     * @param  string
     * @param  array
     * @param  bool
     * @return bool
     */
    public function insert_ignore($table = '', $set = null, $escape = null)
    {
        if ($set !== null) {
            $this->set($set, '', $escape);
        }

        if ($this->_validate_insert($table) === false) {
            return false;
        }

        $sql = $this->_insert_ignore(
            $this->protect_identifiers($this->qb_from[0], true, $escape, false),
            array_keys($this->qb_set),
            array_values($this->qb_set)
        );

        $this->_reset_write();
        return $this->query($sql);
    }

    /**
     * Get UPDATE IGNORE query string
     * @param  string
     * @param  bool
     * @return string
     */
    public function get_compiled_update_ignore($table = '', $reset = true)
    {
        // Combine any cached components with the current statements
        $this->_merge_cache();

        if ($this->_validate_update($table) === false) {
            return false;
        }

        $sql = $this->_update_ignore($this->qb_from[0], $this->qb_set);

        if ($reset === true) {
            $this->_reset_write();
        }

        return $sql;
    }

    /**
     * UPDATE IGNORE
     * @param  string
     * @param  array
     * @param  mixed
     * @param  int
     * @return bool
     */
    public function update_ignore($table = '', $set = null, $where = null, $limit = null)
    {
        // Combine any cached components with the current statements
        $this->_merge_cache();

        if ($set !== null) {
            $this->set($set);
        }

        if ($this->_validate_update($table) === false) {
            return false;
        }

        if ($where !== null) {
            $this->where($where);
        }

        if ( ! empty($limit)) {
            $this->limit($limit);
        }

        $sql = $this->_update_ignore($this->qb_from[0], $this->qb_set);
        $this->_reset_write();
        return $this->query($sql);
    }

}
