<?php
namespace Articles\Model;

use Application\Model\SampleModel;

class Article extends SampleModel
{
    public $id;
    public $title;
    public $text_short;
    public $text;
    public $img;
    public $date;
    public $order;
    public $preview;
}