<?php
namespace Projects\Model;

use Application\Model\SampleModel;

class ProdToProj extends SampleModel
{
    public $id;
    public $project_id;
    public $product_id;
    public $product_type;
    public $order;
}