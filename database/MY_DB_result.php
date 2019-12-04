<?php


class MY_DB_result extends CI_DB_result
{
    /**
     * Extend to allow null param
     * @author Nicolas CLAISSE <n.claisse@santiane.fr>
     * @param string $type
     * @return array
     */
    public function result($type = 'object')
    {
        return $type === null ? parent::result() : parent::result($type);
    }

    /**
     * Extend to allow null type param
     * @author Nicolas CLAISSE <n.claisse@santiane.fr>
     * @param int    $n
     * @param string $type
     * @return mixed
     */
    public function row($n = 0, $type = 'object')
    {
        return $type === null ? parent::row($n) : parent::row($n, $type);
    }

}