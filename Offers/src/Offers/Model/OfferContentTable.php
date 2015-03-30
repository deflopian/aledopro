<?php
namespace Offers\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class OfferContentTable extends SampleTable
{
    protected $table ='offer_products';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new OfferContent());
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
        $where = array('offer_id' => $data['offer_id'], 'product_id' => $data['product_id']);
        $this->delete($where);
    }

    protected function makeArrayOfObjects($resultSet, $idsOnly = false)
    {
        $res = array();
        foreach($resultSet as $row){
            $res[] = $idsOnly ? $row->product_id : $row;
        }

        return $res;
    }
}
