<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 16:52
 */
namespace Reports\Model;

use Application\Model\SampleModel;

class ReportItem extends SampleModel
{
    public $id;
    public $report_id;
    public $linked_id;      // например, id серии
    public $linked_type;    // тип элемента берётся из Catalog\Controller\AdminController
    public $title;          // название элемента, чтобы не обращаться в базу лишний раз
    public $text;           // пока просто дополнительное поле
    public $url;            // ссылка на элемент
}