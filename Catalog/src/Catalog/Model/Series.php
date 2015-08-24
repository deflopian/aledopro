<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class Series extends SampleModel
{
    public $id;
    public $subsection_id;
    public $title;
    public $text;
    public $text_charact;
    public $text_exploit;
    public $text_sphere;
    public $text_dimming;
    public $text_after_dimming;
    public $img;
    public $img_gallery;
    public $order;
    public $visible_title;
    public $sorted_field;
    public $sorted_order;
    public $preview;
    public $deleted;
    public $show_scroll_btn;
    public $is_offer = 0;
    public $fourthTabName;
}