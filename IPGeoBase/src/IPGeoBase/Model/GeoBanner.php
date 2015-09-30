<?php
namespace IPGeoBase\Model;

use Application\Model\SampleModel;

class GeoBanner extends SampleModel
{
    public $id;
    public $region_code;
    public $country_code;
    public $section_type;
    public $section_id;
    public $title;
    public $text;
	public $link;
    public $img;
    public $order;
	public $deleted;
}