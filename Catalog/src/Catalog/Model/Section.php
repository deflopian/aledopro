<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class Section extends SampleModel
{
    public $id;
    public $title;
    public $video;
    public $url;
    public $seo_title;
    public $seo_text;
    public $display_style;
    public $order;
    public $display_name;
    public $deleted = 0; //вместо удаления скрываем раздел, чтобы не устраивать потом головной боли с привязкой кучи говна
    public $sorted_by_user;
    public $is_offer = 0;
}