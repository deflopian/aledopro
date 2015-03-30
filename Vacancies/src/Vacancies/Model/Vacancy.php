<?php
namespace Vacancies\Model;

use Application\Model\SampleModel;

class Vacancy extends SampleModel
{
    public $id;
    public $title;
    public $text;
    public $img;
    public $order;
}