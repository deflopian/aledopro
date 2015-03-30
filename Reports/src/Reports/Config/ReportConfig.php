<?php
namespace Reports\Config;


use Reports\Mapper\ReportMapper;

class ReportConfig
{
    public static $infoByTypes = array(
        ReportMapper::REPORT_TYPE_ORPHAN_SERIES => array(
            "name" => "Отчёт о сериях-сиротах",
            "text" => "Список недавно добавленных серий, которые не привязаны ни к одному подразделу (серии могут повторяться из-за специфики работы Крона)",
        ),
        ReportMapper::REPORT_TYPE_PRODUCT_ZERO_PRICE => array(
            "name" => "Отчёт о продуктах с нулевой ценой",
            "text" => "Список недавно добавленных продуктов без указанной цены",
        ),
    );
}