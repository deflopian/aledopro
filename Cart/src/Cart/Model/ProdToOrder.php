<?php
namespace Cart\Model;

use Application\Model\SampleModel;

class ProdToOrder extends SampleModel
{
    public $order_id;
    public $product_id;
    public $price;
    public $count;
}