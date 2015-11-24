<?php
namespace Cart\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class OrderUserTable extends SampleTable
{
    protected $table = 'order_user';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_order_user';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new OrderUser());
        $this->initialize();
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();
        $this->insert($data);
        return $this->lastInsertValue;
    }

    public function find($id)
    {
        $rowset = $this->select(array('order_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            return false;
        }
        return $row;
    }
}
