<?php
namespace User\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db;
use Zend\Db\TableGateway\AbstractTableGateway;

class RoleLinkerTable extends SampleTable
{
    protected $table ='user_role_linker';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new RoleLinker());
        $this->initialize();
    }

    public function fetchAll() {
        $entities =  array();
        $results = parent::fetchAll();
        foreach ($results as $oneres) {
            $entities[$oneres->user_id] = $oneres->role_id;
        }
        return $entities;
    }

    public function find($id, $columnName = "id")
    {
        $rowset = $this->select(array($columnName => $id));
        $row = $rowset->current();
        if (!$row) {
            return false;
        }
        return $row;
    }

    public function save(SampleModel $entity, $uniqueColumn = 'id')
    {
        $data = $entity->toArray();

        $oldEntity = $this->find($entity->$uniqueColumn, $uniqueColumn);
        if ($oldEntity) {
            foreach($data as $col=>$val){
                if($val === null){
                    unset($data[$col]);
                }
            }
            $this->update($data, array($uniqueColumn => $entity->$uniqueColumn));
        } else {
            $this->insert($data);
        }

        return $this->lastInsertValue;
    }
}
