<?php
namespace Info\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\AbstractTableGateway;

class SeoDataTable extends AbstractTableGateway
{
    protected $table ='seo_data';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new SeoData());
        $this->initialize();
    }

    public function find($type, $id, $old = false)
    {
        $rowset = $this->select(array('type'=> $type,'entity_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            if($old){ return false; }
            $emptyModel = new SeoData();
            $emptyModel->exchangeArray(array(
                'type' => $type,
                'entity_id' => $id
            ));
            return $emptyModel;
        }
        return $row;
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();

        $oldEntity = $this->find($entity->type, $entity->entity_id, true);
        if ($oldEntity) {
            foreach($data as $col=>$val){
                if($val === null){
                    unset($data[$col]);
                }
            }
            $this->update($data, array('type'=> $entity->type,'entity_id' => $entity->entity_id));
        } else {
            $this->insert($data);
        }

        return $this->lastInsertValue;
    }
}
