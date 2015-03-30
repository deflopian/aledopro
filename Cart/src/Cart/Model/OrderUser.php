<?php
namespace Cart\Model;

use Application\Model\SampleModel;

class OrderUser extends SampleModel
{
    public $order_id;
    public $username;
    public $email;
    public $phone;
    public $city;
    public $isSpamed;

    public function getUsername()
    {
        return $this->username;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function getIsSpamed()
    {
        return $this->isSpamed;
    }
}