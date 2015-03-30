<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class DopProdGroup extends SampleModel
{
    public $id;
    public $series_id;
    public $title;
    public $order;
    public $sorted_by_user = 0;
    public $display_style = 0;
}