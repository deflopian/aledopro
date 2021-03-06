<?php
namespace Articles\Model;

use Application\Model\SampleModel;

class ArticleBlock extends SampleModel
{
    public $id;
    public $order = 0;
    public $article_id;
    public $title;
    public $text;
    public $textafter;
    public $img;
    public $img2;
    public $video;
    public $hidden = 0;
}