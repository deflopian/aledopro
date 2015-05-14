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

class CommercialRoom extends SampleModel
{
    /** @var $products Product */
    public $id;
    public $commercial_id; // id КП
    public $title;      // название помещения
    // $products
}