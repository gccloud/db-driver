<?php
defined('BASEPATH') or exit('No direct script access allowed');

 /**
  * Database Driver Class
  *
  * @class       MY_DB_driver
  * @package     CodeIgniter
  * @category    Core
  * @author      Gregory CARRODANO <g.carrodano@gmail.com>
  * @version     20161101
  */
abstract class MY_DB_driver extends CI_DB_driver
{
    /**
     * Class constructor
     * @method __construct
     * @public
     * @params void
     * @return void
     */
	public function __construct($params)
	{
		parent::__construct($params);
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
		return 'INSERT IGNORE INTO '.$table.' ('.implode(', ', $keys).') VALUES ('.implode(', ', $values).')';
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
			$valstr[] = $key.' = '.$val;
		}

		return 'UPDATE IGNORE '.$table.' SET '.implode(', ', $valstr)
			.$this->_compile_wh('qb_where')
			.$this->_compile_order_by()
			.($this->qb_limit ? ' LIMIT '.$this->qb_limit : '');
	}

}
