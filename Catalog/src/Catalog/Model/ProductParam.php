<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class ProductParam extends SampleModel
{
    public $id;
    public $title;
    public $field;
    public $pre_value;
    public $post_value;
    public $text;
    public $term_id;
    public $is_pv; //значение параметра заменяется айдишкой, а само значение попадает в отдельную табличку
}