<?php
namespace User\Model;

use Application\Model\SampleModel;

class ManagerToUser extends SampleModel
{
    public $id;
    public $manager_id;
    public $user_id;
}