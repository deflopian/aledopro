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

class LinkToLinkTable extends SampleTable
{
    protected $table ='link_to_link';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new LinkToLink());
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
        $where = array(
            'link_id_1' => $data['link_id_1'],
            'link_id_2' => $data['link_id_2'],
            'link_type_1' => $data['link_type_1'],
            'link_type_2' => $data['link_type_2']
        );
        $this->delete($where);
    }

    public function find($id, $typeId = 3)
    {
        $res = $this->fetchByConds(array('link_id_1' => $id, 'link_type_1' => $typeId));
        return $res;
    }
}
