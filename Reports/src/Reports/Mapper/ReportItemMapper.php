<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:53
 */
namespace Reports\Mapper;

use Reports\Model\ReportItem;
use Reports\Model\ReportItemsTable;
use Zend\ServiceManager\ServiceLocatorInterface;

class ReportItemMapper {
    private static $instance;
    /** @var $table ReportItemsTable */
    private $table;
    /** @var $sl ServiceLocatorInterface */
    private $sl;

    /**
     * @param $sl ServiceLocatorInterface
     */
    private function __construct($sl){
        $this->sl = $sl;
        $this->table = $sl->get("ReportItemsTable");
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @return ReportItemMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new ReportItemMapper($sl);
        }
        return self::$instance;
    }

    /**
     * @param ReportItem $item
     * @return int
     */
    public function add($item) {
        return $this->table->save($item);
    }

    /**
     * @param int $reportId
     * @param ReportItem[]|array $items
     * @return boolean;
     */
    public function addList($reportId, $items) {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $obj = new ReportItem();
                $obj->exchangeArray($item);
                $item = $obj;
            }
            $item->report_id = $reportId;
            $this->table->save($item);
        }
        return true;
    }

    /**
     * @param int $reportId
     * @return ReportItem[] | array;
     */
    public function getList($reportId) {
        $items = $this->table->fetchByCond('report_id', $reportId);
        return $items;
    }

    /**
     * @param int $id
     */
    public function delete($id) {
        $this->table->del($id);
    }

    /**
     * @param int $reportId
     * @return integer;
     */
    public function deleteList($reportId) {
        $items = $this->table->fetchByCond('report_id', $reportId);
        $res = count($items);
        foreach ($items as $item) {
            $this->table->del($item->id);
        }
        return $res;
    }

    /**
     * @param int $id
     * @return ReportItem | false
     */
    public function get($id) {
        return $this->table->find($id);
    }

}