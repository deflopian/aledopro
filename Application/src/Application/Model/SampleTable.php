<?php
namespace Application\Model;

use Zend\Db\Sql\Ddl\Column\Boolean;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Select;
use Zend\Db;
use Zend\Db\Sql\Where;

class SampleTable extends AbstractTableGateway
{
    protected $table;

    public function fetchIdsByCond($column, $val) {
        $select = new Select($this->table);
        $select->where(array($column => $val));
        $select->columns(array('id'));
        $resultSet = $this->selectWith($select);
        return $resultSet;
    }

    /**
     * Remove all contents of the table
     * @return $this
     */
    public function truncate()
    {
        /** @var $adapter \Zend\Db\Adapter\Adapter */
        $adapter = $this->getAdapter();
        $adapter->query('TRUNCATE TABLE `' . $this->table . '`', $adapter::QUERY_MODE_EXECUTE);

        return $this;
    }

    /**
     * @param $id
     * @return SampleModel|null
     */
    public function find($id)
    {
        $rowset = $this->select(array('id' => $id));
        $row = $rowset->current();
        if (!$row) {
            return false;
        }
        return $row;
    }

    /**
     * @param string $order - порядок сортировки. вид "id asc".
     * @return array
     */
    public function fetchAll($order = "", $idsOnly = false, $limit = 0)
    {
        $select = new Select($this->table);
        if($order){
            $select->order($order);
        }
        if($limit){
            $select->limit($limit);
        }
        $resultSet = $this->selectWith($select);
        return $this->makeArrayOfObjects($resultSet, $idsOnly);
    }

    /**
     * @param $column - колонка поиска
     * @param $value - значение поиска. может быть массивом, тогда будет поиско whereIN
     * @param string $order
     * @param int $limit
     * @return array
     */
    public function fetchByCond($column, $value, $order = "", $limit = 0)
    {
        $select = new Select($this->table);
        if($order){
            $select->order($order);
        }
        if($limit){
            $select->limit($limit);
        }
        $where = new Where();
        if(is_array($value)){
            $where->in($column, $value);
        } else {
            $where->equalTo($column, $value);
        }
        $select->where($where);

        $resultSet = $this->selectWith($select);
        return $this->makeArrayOfObjects($resultSet);
    }

    /**
     * @param array $expr - массив колонка-значение
     * @param array | boolean $exclude - исключенные значения (!=)
     * @param string $order
     * @return array
     */
    public function fetchByConds($expr, $exclude = false, $order = "")
    {
        $select = new Select($this->table);
        if($order){
            $select->order($order);
        }
        $where = new Where();
        if (is_array($exclude)) {
            foreach ($exclude as $column => $value) {
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $where->notEqualTo($column, $val, Db\Sql\Predicate\Predicate::TYPE_IDENTIFIER);
                    }

                } else {
                    $where->notEqualTo($column, $value, Db\Sql\Predicate\Predicate::TYPE_IDENTIFIER);
                }

                $select->where($where);
            }
        }

        foreach ($expr as $column => $value) {

            if(is_array($value)){
                $where->in($column, $value);
            } elseif (is_null($value)) {
                $where->isNull($column);
            } else {
                $where->equalTo($column, $value);
            }
            $select->where($where);
        }

        $resultSet = $this->selectWith($select);
        return $this->makeArrayOfObjects($resultSet);
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();

        $oldEntity = $this->find($entity->id);

        if ($oldEntity) {
            foreach($data as $col=>$val){
                if($val === null){
                    unset($data[$col]);
                }
            }

            $this->update($data, array('id' => $entity->id));

        } else {
            $this->insert($data);
        }


        return $this->lastInsertValue;
    }

    public function saveAll(array $entityArray)
    {
        if (!is_array($entityArray) || count($entityArray) == 0) {
            return;
        }

        $values = "";
        foreach ($entityArray as $entity) {
            $values .= "(";
            foreach ($entity as $oneValue) {
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
        foreach ($entityArray[0] as $key => $val) {
            $updateVal .= '`' . $key . '`=VALUES(`' . $key . '`), ';
        }
        $updateVal = rtrim($updateVal, ', ');
        $sql = 'INSERT INTO ' . $this->table . ' (`' . implode("`, `", array_keys($entityArray[0])) . '`) VALUES ' . $values . '
  ON DUPLICATE KEY UPDATE ' . $updateVal;

        $this->adapter->query($sql, 'execute');

    }

    public function selectLike($column, $regex, $fields = '*', $specialCondition = '')
    {

        if (is_array($fields)) {
            $fields = '(' . implode(',', $fields) . ')';
        }
        if (is_array($column)) {

            $sql_part1 = 'SELECT ' . $fields . ' FROM ' . $this->table . ' WHERE ';

            $sql_part2 = "(";

            $sql_columns = array();
            foreach ($column as $one_column) {
                $sql_columns[] = $one_column . " REGEXP '" . $regex . "'";
            }

            $sql_part2 .= implode(' OR ', $sql_columns);

            $sql_part2 .= ') ';
            $sql_part3 = $specialCondition;
            $sql = $sql_part1 . $sql_part2 . $sql_part3  . " LIMIT 100";

        } else {
            $sql = 'SELECT ' . $fields . ' FROM ' . $this->table . ' WHERE ' . $column . " REGEXP '" . $regex . "' " . $specialCondition . " LIMIT 100";
        }
        $resultAsArray = array();
        try {
            $results = $this->adapter->query($sql, 'execute');
        } catch (\PDOException $e) {
            $results = array();
        }
        foreach ($results as $oneRes) {
            $resultAsArray[] = $oneRes;
        }

        return $resultAsArray;
    }


    public function del($id)
    {
        $this->delete(array('id' => $id));
    }

    protected function makeArrayOfObjects($resultSet, $idsOnly = false)
    {
        $res = array();
        foreach($resultSet as $row){
            $res[] = $idsOnly ? $row->id : $row;
        }

        return $res;
    }
}
