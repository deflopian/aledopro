<?php
namespace User\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class UserTable extends SampleTable
{
    protected $table = 'user';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_user';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new User());
        $this->initialize();
    }

    public function find($id)
    {
        $rowset = $this->select(array('user_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            return false;
        }
        return $row;
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();

        $oldEntity = $this->find($entity->user_id);
        if ($oldEntity) {
            foreach($data as $col=>$val){
                if($val === null){
                    unset($data[$col]);
                }
            }
            $this->update($data, array('user_id' => $entity->user_id));
        } else {
            $this->insert($data);
        }

        return $this->lastInsertValue;
    }

    public function del($id)
    {
        $this->delete(array('user_id' => $id));
    }
}