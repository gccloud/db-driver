<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CodeIgniter Loader Class : standard CI_Loader Class override, which allows to load a custom "DB_query_builder" class extension
 *
 * @class       MY_Loader
 * @package     CodeIgniter
 * @category    Core
 * @author      Gregory CARRODANO <g.carrodano@gmail.com>
 * @version     20161101
 */
class MY_Loader
{
    /**
     * Class constructor
     * @method __construct
     * @public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Database Loader
     * @method __get
     * @public
     * @param  mixed
     * @param  bool
     * @param  bool
     * @return mixed[CI_Loader|bool]
     */
    public function database($params = '', $return = false, $query_builder = true)
    {
        // Grab CI super instance
        $CI =& get_instance();

        // Do we even need to load the database class?
        if ($return === false && $query_builder === null && isset($CI->db) && is_object($CI->db) && ! empty($CI->db->conn_id)) {
            return false;
        }

        // Fetches our custom DB loading function (which will actually use our own DB_driver and DB_query_builder rather than CI ones)
        require_once(APPATH.'third_party/db-driver/database/MY_DB.php');

        if ($return === true) {
            return DB($params, $query_builder);
        }

        // Initialize the db variable. Needed to prevent reference errors with some configurations
        $CI->db = '';

        // Load the DB class
        $CI->db =& DB($params, $query_builder);

        return $this;
    }

}


/* End of file MY_Loader.php */
/* Location: ./application/third_party/db-driver/core/MY_Loader.php */
