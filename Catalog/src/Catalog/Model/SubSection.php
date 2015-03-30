<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class SubSection extends SampleModel
{
    public $id;
    public $section_id;
    public $title;
    public $video;
    public $seo_title;
    public $seo_text;
    public $order;
    public $display_name;
    public $url;
    public $deleted;
    public $sorted_by_user;
    public $is_offer = 0;
}