<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:52
 */
namespace Commercials\Mapper;

use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Commercials\Model\Commercial;
use Commercials\Model\CommercialProd;
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
     * @param integer $userId
     * @return int
     */
    public function add($name, $userId = 0) {
        $data = array();
        $data['title'] = $name;
        $data['user_id'] = $userId;

        if ($userId) {
            $maxUID = $this->getMaxUID($userId);
            $data['uid'] = $maxUID + 1;
        }
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
     * @param $commercial Commercial
     */
    public function actualize($commercial, $user, $discounts, $priceUser) {
        $cm = CatalogMapper::getInstance($this->sl);
        $cpm = CommercialProdMapper::getInstance($this->sl);
        foreach ($commercial->rooms as $room) {
            /** @var CommercialProd $commProd */
            foreach ($room->prods as &$commProd) {
				$priceRequestTable = $this->sl->get('PriceRequestTable');
				$requests = $priceRequestTable->fetchAllSorted();
				
                list($tree, $type) = $cm->getParentTree($commProd->product_id);
                $price = CatalogService::getTruePriceUser($commProd->product->price_without_nds, $priceUser, $tree, $discounts, $commProd->product->opt2, $requests);
                //$price = CatalogService::getTruePrice($commProd->product->price_without_nds);
                $cpm->updatePrice($commProd->id, $price);
            }
        }

        $this->updateSumm($commercial);
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
     * @param $userId
     * @param $uid
     * @param bool|true $fill
     * @param bool|false $recursive
     * @param bool|false $withMainParams
     * @return array|Commercial
     */
    public function getByUID($userId, $uid, $fill = true, $recursive = false, $withMainParams = false) {
        $commercial = $this->table->fetchByConds(array('user_id' => $userId, 'uid' => $uid));
        if ($commercial) {
            $commercial = reset($commercial);
            $commercial->summ = $this->updateSumm($commercial);
        }
        if ($commercial && $fill) {
            $itemsMapper = CommercialRoomMapper::getInstance($this->sl);

            $items = $itemsMapper->getList($commercial->id, $recursive, $withMainParams);

            $commercial->rooms = $items;

        }

        return $commercial;
    }

    /**
     * @param integer $id
     * @param bool $recursive
     * @return Commercial|null
     */
    public function delete($id, $recursive = false) {
        $this->table->del($id);

        if ($recursive) {
            $itemsMapper = CommercialRoomMapper::getInstance($this->sl);

            $itemsMapper->deleteList($id);

        }

        return true;
    }

    /**
     * @param $commercial Commercial
     * @return int
     */
    public function updateSumm($commercial) {
        $crm = CommercialRoomMapper::getInstance($this->sl);
        $rooms = $crm->getList($commercial->id);
        $summ = 0;
        foreach ($rooms as $room) {
            $summ += $crm->updateSumm($room);
        }
        $commercial->summ = $summ;
        if (isset($commercial->rooms)) {
            unset($commercial->rooms);
        }
        $this->table->save($commercial);
        return $summ;
    }

    /**
     * @param integer $id
     * @param array $data
     * @return Commercial|null
     */
    public function update($id, $data) {
        $item = $this->table->find($id);

        foreach ($data as $field => $val) {
            if (isset($item->$field)) {
                $item->$field = $val;
            }
        }
        $this->table->save($item);

        return $item;
    }

    /**
     * @param integer $userId
     * @param integer $uid
     * @param array $data
     * @return Commercial|null
     */
    public function updateByUID($userId, $uid, $data) {
        $item = $this->table->fetchByConds(array('user_id' => $userId, 'uid' => $uid));
        $item = reset($item);
        if (!$item) return false;
        foreach ($data as $field => $val) {

            if (isset($item->$field)) {
                $item->$field = $val;
            }
        }
        $this->table->save($item);

        return $item;
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

    private function getMaxUID($userId) {

        /** @var \Zend\Db\Adapter\Adapter $adapter */
        $adapter = $this->table->getAdapter();
        $resultSet = $adapter->query('SELECT MAX(`uid`) FROM `' . $this->table->table . '` WHERE `user_id`=' . $userId, $adapter::QUERY_MODE_EXECUTE);
        $res = $resultSet->toArray();
        if (count($res)) {
            $res = reset($res[0]);
        }
        return $res ? $res : 0;
    }

    /**
     * @param integer $userId
     * @return Commercial[]
     */
    public function getList($userId) {
        $reports = $this->table->fetchByCond("user_id", $userId);
        return $reports;
    }

    /**
     * @param $report Commercial
     * @param $items CommercialRoom[]|array
     * @return Commercial
     */
    public function addItems($report, $items) {
        $itemsMapper = CommercialRoomMapper::getInstance($this->sl);
        $res = $itemsMapper->addList($report->id, $items);
        if ($res === true) {
            $items = $itemsMapper->getList($report->id);
            $report->items = $items;
        }
        return $report;
    }
}