<?php
namespace News\Model;

use Application\Model\SampleModel;

class News extends SampleModel
{
    public $id;
    public $title;
    public $text_short;
    public $text;
    public $img;
    public $date;
    public $order;
}