<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class FilterField extends SampleModel
{
    public $id;
    public $field_id;                   //id из ProductParamsTable
    public $open            = 0;        //открыт по умолчанию
    public $hidden          = 0;        //не показывать фильтр
    public $cart_param       = 0;        //параметр в корзине
    public $section_id      = 0;        //0 - для всех разделов
    public $section_type    = 0;        //0 - для всех типов (типы указаны в AdminController каталога)
    public $order           = 0;        //0 - для всех типов (типы указаны в AdminController каталога)
    public $is_slider       = 0;
}