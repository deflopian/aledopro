<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:52
 */
namespace Commercials\Mapper;

use Commercials\Model\Commercial;
use Commercials\Model\CommercialRoom;
use Commercials\Model\CommercialsTable;
use Zend\ServiceManager\ServiceLocatorInterface;

class CommercialMapper {
    private static $instance;
    /** @var $table CommercialsTable */
    private $table;
    /** @var $sl ServiceLocatorInterface */
    private $sl;

    const REPORT_TYPE_ALL = 0;
    const REPORT_TYPE_ORPHAN_SERIES = 1;
    const REPORT_TYPE_PRODUCT_ZERO_PRICE = 2;
    const REPORT_TYPE_SEND_MAIL = 3;
    const REPORT_TYPE_ORPHAN_PRODUCTS = 4;

    /**
     * @param $sl ServiceLocatorInterface
     */
    private function __construct($sl){
        $this->sl = $sl;
        $this->table = $sl->get("CommercialsTable");
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @return CommercialMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new CommercialMapper($sl);
        }
        return self::$instance;
    }

    /**
     * @param string $name
     * @param integer $type
     * @param string $text
     * @return int
     */
    public function add($name) {
        $data = array();
        $data['name'] = $name;
        $data['datetime'] = time();

//
//        $report->exchangeArray($data);

        $this->table->insert($data);
        $id = $this->table->adapter->getDriver()->getLastGeneratedValue();
        $report = new Commercial();
        $report->exchangeArray($data);
        $report->id = $id;
        return $report;
    }

    /**
     * @param integer $id
     * @param bool $fill
     * @return Commercial|null
     */
    public function get($id, $fill = true, $recursive = false, $withMainParams = false) {
        $commercial = $this->table->find($id);

        if ($commercial && $fill) {
            $itemsMapper = CommercialRoomMapper::getInstance($this->sl);

            $items = $itemsMapper->getList($id, $recursive, $withMainParams);

            $commercial->rooms = $items;

        }

        return $commercial;
    }

    /**
     * последний из отчётов данного типа
     * (например, чтобы крон дописывал инфу в отчёт, а не создавал каждый раз новый)
     *
     * @param integer $type
     * @param bool $fill
     * @return Commercial|null
     */
    public function getLast($type, $fill=true) {
        /** @var Commercial $report */
        $reports = $this->table->fetchByCond("type", $type, "datetime DESC");
        $report = reset($reports);
        if ($report && $fill) {
            $itemsMapper = CommercialItemMapper::getInstance($this->sl);
            $items = $itemsMapper->getList($report->id);
            $report->items = $items;
        }

        return $report;
    }

    /**
     * @param integer $type
     * @return Commercial[]
     */
    public function getList($type = self::REPORT_TYPE_ALL) {
        $reports = array();
        if ($type == self::REPORT_TYPE_ALL) {
            $reports = $this->table->fetchAll("id DESC", false, 40);
        } else {
            $reports = $this->table->fetchByCond("type", $type);
        }
        return $reports;
    }

    /**
     * @param $report Commercial
     * @param $items CommercialRoom[]|array
     * @return Commercial
     */
    public function addItems($report, $items) {
        $itemsMapper = CommercialItemMapper::getInstance($this->sl);
        $res = $itemsMapper->addList($report->id, $items);
        if ($res === true) {
            $items = $itemsMapper->getList($report->id);
            $report->items = $items;
        }
        return $report;
    }
}