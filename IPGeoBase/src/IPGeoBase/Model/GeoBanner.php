<?php
namespace IPGeoBase\Model;

use Application\Model\SampleModel;

class GeoBanner extends SampleModel
{
    public $id;
    public $region_id;      //если когда-нибудь перейдём на локальную базу адресов, использовать айдишник будет уместнее
    public $region_code;    //код города, null трактуется как Москва
    public $country_code;   //сперва проверяется соответствие страны. У всех код RU
    public $section_type;   //раздел, подраздел или серия
    public $section_id;
    public $title;
    public $text;
    public $img;
    public $order;
}