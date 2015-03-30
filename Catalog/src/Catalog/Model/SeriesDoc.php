<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class SeriesDoc extends SampleModel
{
    public $id;
    public $parent_id;
    public $title;
    public $url;
    public $original_name;
    public $order;
    public $sorted_by_user;
}