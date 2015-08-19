<?php
namespace Developers\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class ProdToProjTable extends SampleTable
{
    protected $table ='product_to_developer';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ProdToProj());
        $this->initialize();
    }

//    public function save(SampleModel $entity)
//    {
//        $data = $entity->toArray();
//        $this->insert($data);
//        return $this->lastInsertValue;
//    }

    public function del($data)
    {
        $where = array('developer_id' => $data['developer_id'], 'product_id' => $data['product_id']);
        $this->delete($where);
    }
}
