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

class DopProdTable extends SampleTable
{
    protected $table ='catalog_series_dopprod';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new DopProd());
        $this->initialize();
    }


    public function save(SampleModel $entity, $new=false)
    {
        $data = $entity->toArray();
        if ($new) {

            $this->update(
                $data,
                array(
                    'dopprod_group_id' => $entity->dopprod_group_id,
                    'product_id' => $entity->product_id
                )
            );
        } else {

            $this->insert($data);
        }

        return $this->lastInsertValue;
    }

    public function deleteWhere($where) {
        if (!is_array($where) || count($where) == 0) {
            return;
        }
        $sql = 'DELETE FROM ' . $this->table . ' WHERE ';
        $args = array();
        foreach ($where as $key => $value) {
            $args[] = '`' . $key . '` = ' . $value;
        }
        $sql .= implode(" AND ", $args);

        $res = $this->adapter->query($sql, 'execute');
        return $res;
    }

    public function del($data)
    {
        $where = array('dopprod_group_id' => $data['dopprod_group_id'], 'product_id' => $data['product_id']);
        $this->delete($where);
    }

    public function find($id, $groupId, $fieldName = 'product_id')
    {
        $result = $this->fetchByConds(array($fieldName => $id, 'dopprod_group_id' => $groupId));
        return (is_array($result) && count($result) > 0) ? $result[0] : $result;
    }
}
