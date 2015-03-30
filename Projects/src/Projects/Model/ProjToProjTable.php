<?php
namespace Projects\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Select;

class ProjToProjTable extends SampleTable
{
    protected $table ='project_to_project';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ProjToProj());
        $this->initialize();
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();
        $this->insert($data);
        return $this->lastInsertValue;
    }

    public function del($data)
    {
        $where = array('proj_id_1' => $data['proj_id_1'], 'proj_id_2' => $data['proj_id_2']);
        $this->delete($where);
        $where = array('proj_id_1' => $data['proj_id_2'], 'proj_id_2' => $data['proj_id_1']);
        $this->delete($where);
    }

    public function find($id)
    {
        $select = new Select($this->table);

        $select->where(
            array(
                new PredicateSet(
                    array(
                        new Operator('proj_id_1', Operator::OPERATOR_EQUAL_TO, $id),
                        new Operator('proj_id_2', Operator::OPERATOR_EQUAL_TO, $id),
                    ), PredicateSet::COMBINED_BY_OR
                )
            )
        );

        $resultSet = $this->selectWith($select);

        $res = array();
        foreach($resultSet as $row){
            if($row->proj_id_1 == $id){
                $res[] = $row->proj_id_2;
            } else {
                $res[] = $row->proj_id_1;
            }
        }

        return $res;
    }
}
