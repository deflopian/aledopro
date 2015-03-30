<?php
namespace Catalog\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class ProductTable extends SampleTable
{
    protected $table ='product';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Product());
        $this->initialize();
    }

    public function deleteWhere($where) {
        if (!is_array($where) || count($where) == 0) {
            return;
        }
        $sql = 'DELETE FROM ' . $this->table . ' WHERE ';
        $args = array();
        foreach ($where as $key => $value) {
            if (is_null($value)) {
                $args[] = '`' . $key . '` IS NULL';
            } else {
                $args[] = '`' . $key . '` = "' . $value . '"';
            }

        }
        $sql .= implode(" AND ", $args);

        $res = $this->adapter->query($sql, 'execute');
        return $res;
    }

    public function updateWhere($where, $what) {

        $sql = 'UPDATE ' . $this->table . ' SET ' . $what . " WHERE " . $where;

        $res = $this->adapter->query($sql, 'execute');
        return $res;
    }

    public function saveAll(array $entityArray)
    {
        if (!is_array($entityArray) || count($entityArray) == 0) {
            return;
        }

        $values = "";
        foreach ($entityArray as $entity) {
            $values .= "(";
            foreach ($entity as $field=>$oneValue) {
                if($field=='file_custom' || $field=='order' || $field=='sorted_by_user' || $field=='add_by_user' || $field=='is_offer'){continue;}

                if (!is_numeric($oneValue)) {
                    $values .= "\"" . addslashes($oneValue) ."\", ";
                } else {
                    $values .= $oneValue . ", ";
                }
            }
            $values = rtrim($values, ', ');
            $values .= "), ";
        }
        $values = rtrim($values, ', ');
        $updateVal = "";
        unset($entityArray[0]['file_custom']);
        unset($entityArray[0]['sorted_by_user']);
        unset($entityArray[0]['add_by_user']);
        unset($entityArray[0]['order']);
        unset($entityArray[0]['is_offer']);
        //unset($entityArray[0]['checked']);
        foreach ($entityArray[0] as $key => $val) {

            $updateVal .= '`' . $key . '`=VALUES(`' . $key . '`), ';
        }

        $updateVal = rtrim($updateVal, ', ');

        $sql = 'INSERT INTO ' . $this->table . ' (`' . implode("`, `", array_keys($entityArray[0])) . '`) VALUES ' . $values . '
  ON DUPLICATE KEY UPDATE ' . $updateVal;

        $this->adapter->query($sql, 'execute');

    }
}
