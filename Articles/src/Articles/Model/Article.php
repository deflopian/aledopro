<?php
namespace Articles\Model;

use Application\Model\SampleModel;

class Article extends SampleModel
{
    public $id;
    public $title;
	public $header1;
	public $header2;
    public $text_short;
    public $alias;
    public $text;
    public $img;
    public $video;
    public $date;
    public $order;
    public $preview;
	public $deleted;
}