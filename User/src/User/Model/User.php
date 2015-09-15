<?php
namespace User\Model;

use Application\Model\SampleModel;

class User extends SampleModel
{
    public $user_id;
    public $username;
    public $email;
    public $phone;
    public $is_spamed;
	public $state;
    public $status;
    public $password;
    public $token;
    public $is_partner;
    public $alias;
    public $manager_id;
    public $partner_group;
    public $city;
    public $god_mode_id;
    public $last_visit;
}