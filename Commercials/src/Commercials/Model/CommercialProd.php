<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:52
 */
namespace Commercials\Model;

use Application\Model\SampleModel;
use Catalog\Model\Product;

class CommercialProd extends SampleModel
{
    /** @var $product Product */
    public $id;
    public $room_id;
    public $product_id;
    public $old_price;
    public $count;
    //$product
}