<?php
namespace Commercials\Model;

use Application\Model\SampleModel;

class Commercial extends SampleModel //toArray & exchangeArray
{
    public $id;
    public $title; //название КП
    public $datetime;
    //public $rooms; //элементы отчёта. Прилинковываются в CommercialMapper
}