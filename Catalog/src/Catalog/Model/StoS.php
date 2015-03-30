<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class StoS extends SampleModel
{
    public $series_id_1; //на самом деле айди серии/продукта/раздела/чёрта лысого
    public $series_id_2; //на самом деле айди серии/продукта/раздела/чёрта лысого
    public $catalog_type_1 = 3; //тип: серия/продукт/раздел/чёрт лысый. Берётся из Catalog\Controller\AdminController
    public $catalog_type_2 = 3; //тип: серия/продукт/раздел/чёрт лысый. Берётся из Catalog\Controller\AdminController
}