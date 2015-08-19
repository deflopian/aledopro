<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:53
 */
namespace Commercials\Mapper;

use Commercials\Model\CommercialRoom;
use Commercials\Model\CommercialRoomsTable;
use Zend\ServiceManager\ServiceLocatorInterface;

class CommercialRoomMapper {
    private static $instance;
    /** @var $table CommercialRoomsTable */
    private $table;
    /** @var $sl ServiceLocatorInterface */
    private $sl;

    /**
     * @param $sl ServiceLocatorInterface
     */
    private function __construct($sl){
        $this->sl = $sl;
        $this->table = $sl->get("CommercialRoomsTable");
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @return CommercialRoomMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new CommercialRoomMapper($sl);
        }
        return self::$instance;
    }

    /**
     * @param CommercialRoom $item
     * @return int
     */
    public function add($item) {
        return $this->table->save($item);
    }


    /**
     * @param $room CommercialRoom
     * @return int
     */
    public function updateSumm($room) {
        $cpm = CommercialProdMapper::getInstance($this->sl);
        $summ = 0;
        $prods = $cpm->getList($room->id);
        foreach ($prods as $prod) {
            $summ += $prod->old_price * $prod->count;
        }
        $room->summ = $summ;
        $this->table->save($room);
        return $summ;
    }

    /**
     * @param integer $id
     * @param array $data
     * @return CommercialRoom|null
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
     * @param int $commercialId
     * @param CommercialRoom[]|array $items
     * @return boolean;
     */
    public function addList($commercialId, $items) {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $obj = new CommercialRoom();
                $obj->exchangeArray($item);
                $item = $obj;
            }
            $item->commercial_id = $commercialId;
            $this->table->save($item);
        }
        return true;
    }

    /**
     * @param int $commercialId
     * @return boolean;
     */
    public function deleteList($commercialId) {
        $items = $this->getList($commercialId, false, false);

        foreach ($items as $item) {
            $this->table->del($item->id);
        }
        return true;
    }

    /**
     * @param int $roomId
     * @return boolean;
     */
    public function delete($roomId) {
        $this->table->del($roomId);
        return true;
    }

    /**
     * @param int $commercialId
     * @param bool $recursive
     * @return CommercialRoom[] | array;
     */
    public function getList($commercialId, $recursive = false, $withMainParams = false) {
        $items = $this->table->fetchByCond('commercial_id', $commercialId, 'order ASC');
        if ($recursive) {
            $cpm = CommercialProdMapper::getInstance($this->sl);
            foreach ($items as &$item) {
                $prods = $cpm->getList($item->id, $recursive, $withMainParams);
                $item->prods = $prods;
            }
        }
        return $items;
    }

    /**
     * @param int $id
     * @param bool $recursive
     * @return CommercialRoom | false
     */
    public function get($id, $recursive = false) {
        $cpm = CommercialProdMapper::getInstance($this->sl);
        $item = $this->table->find($id);
        if ($item && $recursive) {
            $prods = $cpm->getList($item->id, $recursive);
            $item->prods = $prods;
        }

        return $item;
    }

}