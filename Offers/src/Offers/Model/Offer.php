<?php
namespace Offers\Model;

use Application\Model\SampleModel;

class Offer extends SampleModel
{
    public $id;
    public $title;
    public $type;
    public $text;
    public $img;
    public $active;
    public $order;
}