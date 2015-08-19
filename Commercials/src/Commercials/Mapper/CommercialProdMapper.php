<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:53
 */
namespace Commercials\Mapper;

use Application\Service\ApplicationService;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
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
     * @return CommercialProd
     */
    public function add($item) {
        $id = $this->table->save($item);
        $item->id = $id;
        return $item;
    }

    /**
     * @param int $roomId
     * @param CommercialProd[]|array $items
     * @return boolean;
     */
    public function addList($roomId, $items) {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $obj = new CommercialProd();
                $obj->exchangeArray($item);
                $item = $obj;
            }
            $item->room_id = $roomId;
            $this->table->save($item);
        }
        return true;
    }

    /**
     * @param int $roomId
     * @param CommercialProd[]|array $items
     * @return boolean;
     */
    public function updatePrice($commId, $price) {
        $item = $this->table->find($commId);
        if (!$item) {
            return false;
        }
        $item->old_price = $price;
        $this->table->save($item);
        return true;
    }

    /**
     * @param integer $id
     * @param array $data
     * @return CommercialProd|null
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
     * @param int $roomId
     * @param bool $recursive
     * @return CommercialProd[] | array;
     */
    public function getList($roomId, $recursive = false, $withMainParams = false) {
        $items = $this->table->fetchByCond('room_id', $roomId);
        $fileTable = $this->sl->get('FilesTable');
        $filtermParamTable = $this->sl->get('Catalog\Model\FilterParamTable');

//        if ($withMainParams) {
//            $colors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'color_of_light'));
//            $casecolors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'case_color'));
//        }

        if ($recursive) {
            $cm = CatalogMapper::getInstance($this->sl);
            $filterFieldTable = $this->sl->get('FilterFieldTable');
            $sortedParams = array();
            if ($withMainParams) {
                $params = $filtermParamTable->fetchAll();
                $sortedParams = ApplicationService::makeIdArrayFromObjectArray($params);
            }
            foreach ($items as $key => &$item) {
                $product = $cm->getProduct($item->product_id);
                if (!$product) {
                    unset($items[$key]);
                    continue;
                }
//превьюшка для товара

                $file = $fileTable->fetchByCond('uid', $product->id);
                $file = reset($file);

                if ($file) {
                    $product->previewName = $file->name;
                    $product->preview = $file->id;
                }



                if ($withMainParams) {
                    $allParamsTable = $this->sl->get('Catalog\Model\ProductParamsTable');
                    $allParams = $allParamsTable->fetchAll("", false, true);



//                    $item->product->color_of_light = isset($colors[$item->product->color_of_light]) ? $colors[$item->product->color_of_light]->value : $item->product->color_of_light;
//                    $item->product->case_color = isset($casecolors[$item->product->case_color]) ? $casecolors[$item->product->case_color]->value : $item->product->case_color;



                    $series = $cm->getSeriesOne($product->series_id);



                    $subsection = $cm->getSubsection($series->subsection_id);
                    $filters = $filterFieldTable->fetchAll($series->subsection_id, \Catalog\Controller\AdminController::SUBSECTION_TABLE, $subsection->section_id, "order ASC");

                    $mainParams = array();
                    foreach ($filters as $fkey => $filter) {

                        if ($filter->cart_param == 1) {
                            $f = $allParams[$filter->field_id];
                            $fName = $f->field;
                            if ($product->$fName && in_array($f->field, CatalogService::$intFields)) {

                                $product->$fName = isset($sortedParams[$product->$fName]) ? $sortedParams[$product->$fName]->value : $product->$fName;

                            }
                            if ($product->$fName) {
                                $mainParams[$f->field] = $f->title;
                                $product->$fName = $f->pre_value . $product->$fName . $f->post_value;
                            }
                        }
                    }
                    $item->mainParams = $mainParams;
                }

                $item->product = $product;
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

    /**
     * @param int $id
     * @return CommercialProd | false
     */
    public function delete($id) {
        return $this->table->del($id);
    }

}