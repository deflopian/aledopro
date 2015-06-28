<?php
namespace Developers\Model;

use Application\Model\SampleModel;

class Developer extends SampleModel
{
    public $id;
    public $title;
    public $img;
    public $text;
    public $pdf;
    public $rubric_id;
    public $preview;
    public $order;
}