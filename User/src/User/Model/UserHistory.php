<?php
namespace User\Model;

use Application\Model\SampleModel;

class UserHistory extends SampleModel
{
    public $id;
    public $user_id;
    public $actionType;
    public $url;
    public $timer;
    public $to_user_id;
}