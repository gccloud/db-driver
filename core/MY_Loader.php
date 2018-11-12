<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CodeIgniter Loader Class : standard CI_Loader Class override, which allows to load a custom "DB_query_builder" class extension
 *
 * @class       MY_Loader
 * @package     CodeIgniter
 * @category    Core
 * @author      Gregory CARRODANO <g.carrodano@gmail.com>
 * @version     20181112
 */
class MY_Loader extends CI_Loader
{
    /**
     * Class constructor
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Database Loader
     * @param  mixed
     * @param  bool
     * @param  bool
     * @return mixed
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
        require_once(APPPATH.'third_party/db-driver/database/MY_DB.php');

        if ($return === true) {
            return DB($params, $query_builder);
        }

        // Initialize the db variable. Needed to prevent reference errors with some configurations
        $CI->db = '';

        // Load the DB class
        $CI->db =& DB($params, $query_builder);

        return $this;
    }

    /**
     * Model Loader
     *
     * Loads and instantiates models.
     *
     * @param    string    $model        Model name
     * @param    string    $name        An optional object name to assign to
     * @param    bool    $db_conn    An optional database connection configuration to initialize
     * @return    object
     */
    public function model($model, $name = '', $db_conn = FALSE)
    {
        if (empty($model))
        {
            return $this;
        }
        elseif (is_array($model))
        {
            foreach ($model as $key => $value)
            {
                is_int($key) ? $this->model($value, '', $db_conn) : $this->model($key, $value, $db_conn);
            }

            return $this;
        }

        $path = '';

        // Is the model in a sub-folder? If so, parse out the filename and path.
        if (($last_slash = strrpos($model, '/')) !== FALSE)
        {
            // The path is in front of the last slash
            $path = substr($model, 0, ++$last_slash);

            // And the model name behind it
            $model = substr($model, $last_slash);
        }

        if (empty($name))
        {
            $name = $model;
        }

        if (in_array($name, $this->_ci_models, TRUE))
        {
            return $this;
        }

        $CI =& get_instance();
        if (isset($CI->$name))
        {
            log_message('error', 'The model name you are loading is the name of a resource that is already being used: '.$model);
            show_error('The model name you are loading is the name of a resource that is already being used: '.$model);
        }

        if ($db_conn !== FALSE && ! class_exists('CI_DB', FALSE))
        {
            if ($db_conn === TRUE)
            {
                $db_conn = '';
            }

            $this->database($db_conn, FALSE, TRUE);
        }

        // Note: All of the code under this condition used to be just:
        //
        //       load_class('Model', 'core');
        //
        //       However, load_class() instantiates classes
        //       to cache them for later use and that prevents
        //       MY_Model from being an abstract class and is
        //       sub-optimal otherwise anyway.
        if ( ! class_exists('CI_Model', FALSE))
        {
            $app_path = APPPATH.'core'.DIRECTORY_SEPARATOR;
            if (file_exists($app_path.'Model.php'))
            {
                require_once($app_path.'Model.php');
                if ( ! class_exists('CI_Model', FALSE))
                {
                    log_message('error', $app_path."Model.php exists, but doesn't declare class CI_Model");
                    show_error($app_path."Model.php exists, but doesn't declare class CI_Model");
                }
            }
            elseif ( ! class_exists('CI_Model', FALSE))
            {
                require_once(BASEPATH.'core'.DIRECTORY_SEPARATOR.'Model.php');
            }

            $class = config_item('subclass_prefix').'Model';
            if (file_exists($app_path.$class.'.php'))
            {
                require_once($app_path.$class.'.php');
                if ( ! class_exists($class, FALSE))
                {
                    log_message('error', $app_path.$class.".php exists, but doesn't declare class ".$class);
                    show_error($app_path.$class.".php exists, but doesn't declare class ".$class);
                }
            }
        }

        $model = ucfirst($model);
        if ( ! class_exists($model, FALSE))
        {
            foreach ($this->_ci_model_paths as $mod_path)
            {
                if ( ! file_exists($mod_path.'models/'.$path.$model.'.php'))
                {
                    continue;
                }

                require_once($mod_path.'models/'.$path.$model.'.php');
                if ( ! class_exists($model, FALSE))
                {
                    log_message('error', $mod_path."models/".$path.$model.".php exists, but doesn't declare class ".$model);
                    show_error($mod_path."models/".$path.$model.".php exists, but doesn't declare class ".$model);
                }

                break;
            }

            if ( ! class_exists($model, FALSE))
            {
                log_message('error', 'Unable to locate the model you have specified: '.$model);
        		show_error('Unable to locate the model you have specified: '.$model);
            }
        }
        elseif ( ! is_subclass_of($model, 'CI_Model'))
        {
            log_message('error', "Class ".$model." already exists and doesn't extend CI_Model");
            show_error("Class ".$model." already exists and doesn't extend CI_Model");
        }

        $this->_ci_models[] = $name;
        $CI->$name = new $model();
        return $this;
    }

}


/* End of file MY_Loader.php */
/* Location: ./application/third_party/db-driver/core/MY_Loader.php */
