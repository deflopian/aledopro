<?php
namespace Cart\Model;

use Application\Model\SampleModel;

class Order extends SampleModel
{
    public $id;
    public $user_id;
    public $summ;
    public $comment;
    public $orderState;
    public $file;
    public $date;
    public $finished;
}