<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 18.06.14
 * Time: 10:09
 */

namespace Catalog\Service;


class Hierarchy {
    private $products = array();
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Hierarchy();
        }
        return self::$instance;
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function getProductHierarchy($id) {
        return array_key_exists($id, $this->products) ? $this->products[$id] : array();
    }

    public function setProductHierarchy($id, $arr) {
        foreach ($arr as $key => $val) {
            $this->products[$id][$key] = $val;
        }

        return $this;
    }

} 