<?php
namespace Vacancies\Model;

use Application\Model\SampleModel;

class VacancyRequest extends SampleModel
{
    public $id;
    public $vacancy;
    public $custom_vacancy;
    public $name;
    public $mail;
    public $file;
    public $letter;
    public $date;
}