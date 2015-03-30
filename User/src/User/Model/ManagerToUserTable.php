<?php
namespace User\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class ManagerToUserTable extends SampleTable
{
    protected $table ='manager_to_user';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ManagerToUser());
        $this->initialize();
    }

    public function findByManager($id)
    {
        $rowset = $this->select(array('manager_id' => $id));

        if (!$rowset) {
            return false;
        }
        return $rowset;
    }

    public function findByUser($id) {
        $rowset = $this->select(array('user_id' => $id));

        if (!$rowset) {
            return false;
        }
        return $rowset;
    }

    public function findByPair($managerId, $userId) {
        $rowset = $this->select(array('user_id' => $userId, 'manager_id' => $managerId));
        $row = $rowset->current();
        if (!$row) {
            return false;
        }
        return $row;
    }

    public function save(ManagerToUser $entity)
    {
        $data = $entity->toArray();

        $oldEntity = $this->findByPair($entity->manager_id, $entity->user_id);
        if ($oldEntity) {
            foreach($data as $col=>$val){
                if($val === null){
                    unset($data[$col]);
                }
            }
            $this->update($data, array('user_id' => $entity->user_id, 'manager_id' => $entity->manager_id));
        } else {
            $this->insert($data);
        }

        return $this->lastInsertValue;
    }

    public function delByUser($id)
    {
        $this->delete(array('user_id' => $id));
    }

    public function delByManager($id)
    {
        $this->delete(array('manager_id' => $id));
    }

    public function delByPair($managerId, $userId)
    {
        $this->delete(array('manager_id' => $managerId, 'user_id' => $userId));
    }
}