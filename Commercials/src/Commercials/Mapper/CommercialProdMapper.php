<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:53
 */
namespace Commercials\Mapper;

use Catalog\Mapper\CatalogMapper;
use Commercials\Model\CommercialProdsTable;
use Commercials\Model\CommercialProd;
use Zend\ServiceManager\ServiceLocatorInterface;

class CommercialProdMapper {
    private static $instance;
    /** @var $table CommercialProdsTable */
    private $table;
    /** @var $sl ServiceLocatorInterface */
    private $sl;

    /**
     * @param $sl ServiceLocatorInterface
     */
    private function __construct($sl){
        $this->sl = $sl;
        $this->table = $sl->get("CommercialProdsTable");
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @return CommercialProdMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new CommercialProdMapper($sl);
        }
        return self::$instance;
    }

    /**
     * @param CommercialProd $item
     * @return int
     */
    public function add($item) {
        return $this->table->save($item);
    }

    /**
     * @param int $reportId
     * @param CommercialProd[]|array $items
     * @return boolean;
     */
    public function addList($commercialId, $items) {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $obj = new CommercialProd();
                $obj->exchangeArray($item);
                $item = $obj;
            }
            $item->commercial_id = $commercialId;
            $this->table->save($item);
        }
        return true;
    }

    /**
     * @param int $roomId
     * @param bool $recursive
     * @return CommercialProd[] | array;
     */
    public function getList($roomId, $recursive = false, $withMainParams = false) {
        $items = $this->table->fetchByCond('room_id', $roomId);
        $fileTable = $this->sl->get('FilesTable');
        if ($recursive) {
            $cm = CatalogMapper::getInstance($this->sl);
            $filterFieldTable = $this->sl->get('FilterFieldTable');
            foreach ($items as &$item) {
                $product = $cm->getProduct($item->product_id);
//превьюшка для товара

                $file = $fileTable->fetchByCond('uid', $product->id);
                $file = reset($file);

                if ($file) {
                    $product->previewName = $file->name;
                    $product->preview = $file->id;
                }
                $item->product = $product;
                if ($withMainParams) {
                    $series = $cm->getSeriesOne($product->series_id);
                    $allParamsTable = $this->sl->get('Catalog\Model\ProductParamsTable');
                    $allParams = $allParamsTable->fetchAll("", false, true);
                    $filters = $filterFieldTable->fetchAll($series->subsection_id, \Catalog\Controller\AdminController::SUBSECTION_TABLE, 0, "order ASC");
                    $mainParams = array();
                    foreach ($filters as $fkey => $filter) {
                        if ($filter->cart_param == 1) {
                            $mainParams[$allParams[$filter->field_id]->field] = $allParams[$filter->field_id]->title;
                        }
                    }
                    $item->mainParams = $mainParams;
                }
            }
        }
        return $items;
    }

    /**
     * @param int $id
     * @return CommercialProd | false
     */
    public function get($id) {
        return $this->table->find($id);
    }

}