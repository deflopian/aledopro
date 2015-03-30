<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class LinkToLink extends SampleModel
{
    public $link_id_1; //на самом деле айди серии/продукта/раздела/чёрта лысого
    public $link_id_2; //на самом деле айди серии/продукта/раздела/чёрта лысого
    public $link_type_1 = 3; //тип: серия/продукт/раздел/чёрт лысый. Берётся из Catalog\Controller\AdminController
    public $link_type_2 = 3; //тип: серия/продукт/раздел/чёрт лысый. Берётся из Catalog\Controller\AdminController
}