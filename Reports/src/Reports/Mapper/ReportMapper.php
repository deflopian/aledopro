<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:52
 */
namespace Reports\Mapper;

use Reports\Model\Report;
use Reports\Model\ReportItem;
use Reports\Model\ReportsTable;
use Zend\ServiceManager\ServiceLocatorInterface;

class ReportMapper {
    private static $instance;
    /** @var $table ReportsTable */
    private $table;
    /** @var $sl ServiceLocatorInterface */
    private $sl;

    const REPORT_TYPE_ALL = 0;
    const REPORT_TYPE_ORPHAN_SERIES = 1;
    const REPORT_TYPE_PRODUCT_ZERO_PRICE = 2;
    const REPORT_TYPE_SEND_MAIL = 3;
    const REPORT_TYPE_ORPHAN_PRODUCTS = 4;
    const REPORT_TYPE_NEW_PRODUCTS = 5;
    const REPORT_TYPE_WITHOUT_PREVIEW = 6;

    /**
     * @param $sl ServiceLocatorInterface
     */
    private function __construct($sl){
        $this->sl = $sl;
        $this->table = $sl->get("ReportsTable");
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @return ReportMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new ReportMapper($sl);
        }
        return self::$instance;
    }

    /**
     * @param string $name
     * @param integer $type
     * @param string $text
     * @return int
     */
    public function add($name, $type, $text = "") {
        $data = array();
        $data['name'] = $name;
        $data['type'] = $type;
        $data['text'] = $text;
        $data['datetime'] = time();

//
//        $report->exchangeArray($data);

        $this->table->insert($data);
        $id = $this->table->adapter->getDriver()->getLastGeneratedValue();
        $report = new Report();
        $report->exchangeArray($data);
        $report->id = $id;
        return $report;
    }

    /**
     * @param integer $id
     * @param bool $fill
     * @return Report|null
     */
    public function get($id, $fill = true) {
        $report = $this->table->find($id);

        if ($report && $fill) {
            $itemsMapper = ReportItemMapper::getInstance($this->sl);
            $items = $itemsMapper->getList($id);
            $report->items = $items;
        }

        return $report;
    }

    /**
     * последний из отчётов данного типа
     * (например, чтобы крон дописывал инфу в отчёт, а не создавал каждый раз новый)
     *
     * @param integer $type
     * @param bool $fill
     * @return Report|null
     */
    public function getLast($type, $fill=true) {
        /** @var Report $report */
        $reports = $this->table->fetchByCond("type", $type, "datetime DESC");
        $report = reset($reports);
        if ($report && $fill) {
            $itemsMapper = ReportItemMapper::getInstance($this->sl);
            $items = $itemsMapper->getList($report->id);
            $report->items = $items;
        }

        return $report;
    }

    /**
     * @param integer $type
     * @return Report[]
     */
    public function getList($type = self::REPORT_TYPE_ALL) {
        $reports = array();
        if ($type == self::REPORT_TYPE_ALL) {
            $reports = $this->table->fetchAll("id DESC", false, 20);
        } else {
            $reports = $this->table->fetchByCond("type", $type, "id DESC", 20);
        }
        return $reports;
    }

    public function delete($id, $cascade = false) {
        $this->table->del($id);
        if ($cascade) {
            $itemsMapper = ReportItemMapper::getInstance($this->sl);
            $itemsMapper->deleteList($id);
        }
    }

    /**
     * @param $report Report
     * @param $items ReportItem[]|array
     * @return Report
     */
    public function addItems($report, $items) {
        $itemsMapper = ReportItemMapper::getInstance($this->sl);
        $res = $itemsMapper->addList($report->id, $items);
        if ($res === true) {
            $items = $itemsMapper->getList($report->id);
            $report->items = $items;
        }
        return $report;
    }
}