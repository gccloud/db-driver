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
abstract class MY_DB_query_builder extends CI_DB_query_builder
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
        if (!empty($this->qb_orderby)) {
            $orderby = $this->qb_orderby;
            $this->qb_orderby = null;
        }

        $result = ($this->qb_distinct === true) ? $this->query($this->_count_string . $this->protect_identifiers('numrows') . "\nFROM (\n" . $this->_compile_select() . "\n) CI_count_all_results") : $this->query($this->_compile_select($this->_count_string . $this->protect_identifiers('numrows')));

        if ($reset === true) {
            $this->_reset_select();
        } elseif (!isset($this->qb_orderby)) {
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
                $this->protect_identifiers($this->qb_from[0], true, null, false), array_keys($this->qb_set), array_values($this->qb_set)
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
                $this->protect_identifiers($this->qb_from[0], true, $escape, false), array_keys($this->qb_set), array_values($this->qb_set)
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

        if (!empty($limit)) {
            $this->limit($limit);
        }

        $sql = $this->_update_ignore($this->qb_from[0], $this->qb_set);
        $this->_reset_write();
        return $this->query($sql);
    }

    /**
     * Insert ignore statement
     * @param  string
     * @param  array
     * @param  array
     * @return string
     */
    protected function _insert_ignore($table, $keys, $values)
    {
        return 'INSERT IGNORE INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
    }

    /**
     * Update statement
     * @param  string
     * @param  array
     * @return string
     */
    protected function _update_ignore($table, $values)
    {
        foreach ($values as $key => $val) {
            $valstr[] = $key . ' = ' . $val;
        }

        return 'UPDATE IGNORE ' . $table . ' SET ' . implode(', ', $valstr)
                . $this->_compile_wh('qb_where')
                . $this->_compile_order_by()
                . ($this->qb_limit ? ' LIMIT ' . $this->qb_limit : '');
    }

    /**
	 * Compile WHERE, HAVING statements
	 *
	 * Escapes identifiers in WHERE and HAVING statements at execution time.
	 *
	 * Required so that aliases are tracked properly, regardless of whether
	 * where(), or_where(), having(), or_having are called prior to from(),
	 * join() and dbprefix is added only if needed.
	 *
	 * @param	string	$qb_key	'qb_where' or 'qb_having'
	 * @return	string	SQL statement
	 */
	protected function _compile_wh($qb_key)
	{
		if (count($this->$qb_key) > 0)
		{
			for ($i = 0, $c = count($this->$qb_key); $i < $c; $i++)
			{
				// Is this condition already compiled?
				if (is_string($this->{$qb_key}[$i]))
				{
					continue;
				}
				elseif ($this->{$qb_key}[$i]['escape'] === FALSE)
				{
					$this->{$qb_key}[$i] = $this->{$qb_key}[$i]['condition'];
					continue;
				}

				// Split multiple conditions
				$conditions = preg_split(
					'/((?:^|\s+)AND\s+|(?:^|\s+)OR\s+)/i',
					$this->{$qb_key}[$i]['condition'],
					-1,
					PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
				);

				for ($ci = 0, $cc = count($conditions); $ci < $cc; $ci++)
				{
				    $op = $this->_get_operator($conditions[$ci]);
					if (($op) === FALSE
                        OR strlen($op) > 32767 // max limit on regex size - https://stackoverflow.com/questions/25310999/what-is-the-maximum-length-of-a-regular-expression
						OR ! preg_match('/^(\(?)(.*)('.preg_quote($op, '/').')\s*(.*(?<!\)))?(\)?)$/i', $conditions[$ci], $matches))
					{
						continue;
					}

					// $matches = array(
					//	0 => '(test <= foo)',	/* the whole thing */
					//	1 => '(',		/* optional */
					//	2 => 'test',		/* the field name */
					//	3 => ' <= ',		/* $op */
					//	4 => 'foo',		/* optional, if $op is e.g. 'IS NULL' */
					//	5 => ')'		/* optional */
					// );

					if ( ! empty($matches[4]))
					{
						$this->_is_literal($matches[4]) OR $matches[4] = $this->protect_identifiers(trim($matches[4]));
						$matches[4] = ' '.$matches[4];
					}

					$conditions[$ci] = $matches[1].$this->protect_identifiers(trim($matches[2]))
						.' '.trim($matches[3]).$matches[4].$matches[5];
				}

				$this->{$qb_key}[$i] = implode('', $conditions);
			}

			return ($qb_key === 'qb_having' ? "\nHAVING " : "\nWHERE ")
				.implode("\n", $this->$qb_key);
		}

		return '';
	}
}
