<?php
namespace Discount\Model;

use Application\Model\SampleModel;

class Discount extends SampleModel
{
    public $id;
    public $user_id; //айди партнёра
    public $section_id; //айди серии/продукта/раздела/чёрта лысого
    public $section_type; //тип: серия/продукт/раздел/чёрт лысый. Берётся из Catalog\Controller\AdminController
    public $discount; //скидка в процентах
    public $is_group; //скидка в процентах
}