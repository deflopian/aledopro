<?php
namespace User\Model;

use Application\Model\SampleModel;

class UserRole extends SampleModel
{
    public $role_id;
    public $is_default;
    public $parent;
}