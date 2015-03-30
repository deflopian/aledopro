<?php
namespace Reports\Model;

use Application\Model\SampleModel;

class Report extends SampleModel //toArray & exchangeArray
{
    public $id;
    public $name; //название отчёта
    public $text; //текст отчёта (если нет нужды в отдельных элементах, лучше использовать текст отчёта)
    public $datetime; //дата генерации отчёта. Заполняется автоматически
    public $type; //тип отчёта (типы указаны в ReportMapper)
    //public $items; //элементы отчёта. Прилинковываются в ReportMapper
}