<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

/**
 * Набор параметров (за исключением диапазонов) и их возможных значений
 * Class FilterParam
 * @package Catalog\Model
 */
class FilterParam extends SampleModel
{
    public $id;
    public $field;
    public $value;
}