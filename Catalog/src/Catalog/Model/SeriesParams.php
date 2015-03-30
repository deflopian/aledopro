<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

/**
 * Class SeriesParams
 * Определяет мин-макс параметры, по которым будет происходить фильтрация внутри серии
 * В довесок, позволяет нам выполнять подобную операцию и для подразделов
 * Define min and max value of product params in each series for the filtration
 * @package Catalog\Model
 */
class SeriesParams extends SampleModel
{
    public $id = 0;
    public $series_id = 0;
    public $subsection_id = 0;
    public $min_price = 0;
    public $max_price = 0;
    public $min_power = 0;
    public $max_power = 0;
    public $min_viewing_angle = 0;
    public $max_viewing_angle = 0;
    public $min_luminous_flux = 0;
    public $max_luminous_flux = 0;
}