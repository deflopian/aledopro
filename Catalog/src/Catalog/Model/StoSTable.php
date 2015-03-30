<?php
namespace Catalog\Model;

use Application\Model\SampleModel;
use Zend\Db\Sql\Predicate;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateSet;

class StoSTable extends SampleTable
{
    protected $table ='series_to_series';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new StoS());
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
        $where = array('series_id_1' => $data['series_id_1'], 'series_id_2' => $data['series_id_2']);
        $this->delete($where);

        $where = array('series_id_1' => $data['series_id_2'], 'series_id_2' => $data['series_id_1']);
        $this->delete($where);
    }

    public function find($id, $typeId = 3)
    {
        $select = new Select($this->table);

        $select->where(
            array(
                new PredicateSet(
                    array(
                        new PredicateSet(
                            array(
                                new Operator('series_id_1', Operator::OPERATOR_EQUAL_TO, $id),
                                new Operator('catalog_type_1', Operator::OPERATOR_EQUAL_TO, $typeId),
                            ),  PredicateSet::COMBINED_BY_AND
                        ),
                        new PredicateSet(
                            array(
                                new Operator('series_id_2', Operator::OPERATOR_EQUAL_TO, $id),
                                new Operator('catalog_type_2', Operator::OPERATOR_EQUAL_TO, $typeId),
                            ),  PredicateSet::COMBINED_BY_AND
                        )
                    ),PredicateSet::COMBINED_BY_OR
                )
            )
        );

        $resultSet = $this->selectWith($select);

        $res = array();
        foreach($resultSet as $row){
            if($row->series_id_1 == $id){
                $res[] = array($row->series_id_2, $row->catalog_type_2);
            } else {
                $res[] = array($row->series_id_1, $row->catalog_type_1);
            }
        }

        return $res;
    }
}
