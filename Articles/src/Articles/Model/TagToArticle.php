<?php
namespace Articles\Model;

use Application\Model\SampleModel;

class TagToArticle extends SampleModel
{
    public $id;
    public $tag_id;
    public $article_id;
}