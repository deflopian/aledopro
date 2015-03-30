<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 18.06.14
 * Time: 10:09
 */

namespace Catalog\Service;


class ProductsAggregator {
    private $products = array();
    private $pBySEid = array();

    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ProductsAggregator();
        }
        return self::$instance;
    }

    /**
     * @param integer $seId
     * @return \Catalog\Model\Product[]
     *
     */
    public function getProducts($seId = 0)
    {
        if ($seId != 0) {
            $products = array();
            if (array_key_exists($seId, $this->pBySEid)) {
                foreach ($this->pBySEid[$seId] as $oneProdId) {
                    $products[] = $this->products[$oneProdId];
                }
            }
            return $products;
        }
        return $this->products;
    }

    public function addProducts($products) {
        if (is_array($products)) {
            foreach ($products as $one) {
                if (!array_key_exists($one->id, $this->products)) {
                    $this->products[$one->id] = $one;
                    $this->pBySEid[$one->series_id][] = $one->id;
                }
            }
        } else {
            $this->products[$products->id] = $products;
            $this->pBySEid[$products->series_id][] = $products->id;
        }

        return $this;
    }
} 