<?php
namespace Contacts\Model;

use Application\Model\SampleModel;

class Contact extends SampleModel
{
    public $id;
    public $adress;
    public $adress_storage;
    public $work_time;
    public $phone;
    public $fax;
    public $mail;
}