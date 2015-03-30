<?php
namespace Projects\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;

class ProjectImgTable extends SampleTable
{
    protected $table ='project_img';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ProjectImg());
        $this->initialize();
    }

    public function fetchAll($order = "", $idsOnly = false)
    {
        $select = new Select($this->table);
        if($order){
            $select->order($order);
        }
        $resultSet = $this->selectWith($select);

        $res = array();
        foreach($resultSet as $row){
            $res[$row->parent_id][] = $row;
        }
        return $res;
    }
}
