<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class ProductInMarket extends SampleModel
{
    public $id;
    public $bid = 0;
    public $purchase = 0;
    public $order = 0;
    public $sorted_by_user = 0;
    public $alias = 0;
}