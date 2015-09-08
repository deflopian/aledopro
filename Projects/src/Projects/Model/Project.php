<?php
namespace Projects\Model;

use Application\Model\SampleModel;

class Project extends SampleModel
{
    public $id;
    public $title;
    public $adress;
    public $text;
    public $img;
    public $pdf;
    public $rubric_id;
    public $preview;
    public $order;
    public $deleted;
}