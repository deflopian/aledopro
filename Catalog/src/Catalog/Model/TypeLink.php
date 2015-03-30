<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class TypeLink extends SampleModel
{
    public $ty;
    public $product_id;
    public $order;
    public $sorted_by_user = 0;
}