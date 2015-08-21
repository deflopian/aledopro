<?php
namespace Vacancies\Model;

use Application\Model\SampleModel;

class Vacancy extends SampleModel
{
    public $id;
    public $title;
    public $salary;
    public $city;
    public $skill;
    public $duties;
    public $requirements;
    public $conditions;
    public $address;
    public $hours;
    public $img;
    public $order;
    public $active;
}