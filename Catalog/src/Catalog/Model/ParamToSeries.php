<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

/**
 * Набор параметров (за исключением диапазонов) и их возможных значений
 * Class ParamToSeries
 * @package Catalog\Model
 */
class ParamToSeries extends SampleModel
{
    public $id;
    public $param_id;
    public $series_id;
}