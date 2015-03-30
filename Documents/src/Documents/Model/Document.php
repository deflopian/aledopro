<?php
namespace Documents\Model;

use Application\Model\SampleModel;

class Document extends SampleModel
{
    public $id;
    public $file;
    public $img;
    public $order;
    public $title;
    public $type; //тип документа (инструкция, каталог, сертификат...)
}