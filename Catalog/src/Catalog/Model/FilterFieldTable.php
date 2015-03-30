<?php
namespace Catalog\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Catalog\Controller\AdminController;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\AbstractTableGateway;

class FilterFieldTable extends SampleTable
{
    protected $table = 'filter_field';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new FilterField());
        $this->initialize();
    }

    public function getMaxId() {
        $select = new Select($this->table);
        //
        $stmt = $this->adapter->createStatement("SHOW TABLE STATUS LIKE '$this->table'");
        $stmt->prepare();
        $result = $stmt->execute();
        $resultSet = new ResultSet;
        $resultSet->initialize($result);

        $res = $resultSet->toArray();

        return $res[0]['Auto_increment'];
    }

    public function fetchAll($sectionId, $sectionType, $parentSectionId = 0, $order = "") {

        $selectDefault = new Select($this->table);
        if($order){
            $selectDefault->order($order);
        }
        $where = new Where();

        $where->equalTo('section_id', 0);
        $where->equalTo('section_type', AdminController::SECTION_TABLE);
        $selectDefault->where($where);

        /** @var FilterField[] $defaultSet */
        $defaultSet = $this->selectWith($selectDefault);

        $res = array();
        foreach($defaultSet as $row){
            $res[$row->field_id] = $row;
        }

        if ($sectionType==AdminController::SUBSECTION_TABLE && $parentSectionId > 0) {
            $selectParent = new Select($this->table);
            if($order){
                $selectParent->order($order);
            }
            $where = new Where();
            $where->equalTo('section_id', $parentSectionId);
            $where->equalTo('section_type', AdminController::SECTION_TABLE);
            $selectParent->where($where);

            /** @var FilterField[] $resultSet */
            $resultSet = $this->selectWith($selectParent);
            foreach($resultSet as $row){
                $res[$row->field_id] = $row;
            }
        }

        $selectSpecial = new Select($this->table);
        if($order){
            $selectSpecial->order($order);
        }
        $where = new Where();
        $where->equalTo('section_id', $sectionId);
        $where->equalTo('section_type', $sectionType);
        $selectSpecial->where($where);

        /** @var FilterField[] $resultSet */
        $resultSet = $this->selectWith($selectSpecial);
        foreach($resultSet as $row){
            $res[$row->field_id] = $row;
        }



        if ($order == "order ASC") {
            uasort($res, function ($a, $b)
            {

                if ((int)$a->order == (int)$b->order) {
                    return 0;
                }
                return ((int)$a->order < (int)$b->order) ? -1 : 1;
            });

        }

        return $res;
    }

    public function deleteAllBySection($sectionId, $sectionType) {
        if ($sectionId > 0 && $sectionType > 0) {
            $this->delete(array('section_id' => $sectionId, 'section_type' => $sectionType));
        }

    }
}
