<?php
namespace Catalog\Model;

use Application\Model\SampleModel;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\AbstractTableGateway;

class EqualParamsTable extends AbstractTableGateway
{
    protected $table = 'series_equal_params';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new EqualParams());
        $this->initialize();
    }

    public function find($id, $checkOld = false)
    {
        $rowset = $this->select(array('series_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            return false;
        }

        return $checkOld ? $row :\Zend\Json\Json::decode($row->fields);
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();

        $oldEntity = $this->find($entity->series_id, true);
        if ($oldEntity) {
            foreach($data as $col=>$val){
                if($val === null){
                    unset($data[$col]);
                }
            }
            $this->update($data, array('series_id' => $entity->series_id));
        } else {
            $this->insert($data);
        }

        return $this->lastInsertValue;
    }
}
