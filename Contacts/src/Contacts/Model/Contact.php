<?php
namespace Contacts\Model;

use Application\Model\SampleModel;

class Contact extends SampleModel
{
    public $id;
    public $title;
    public $adress;
    public $adress_storage;
    public $work_time;
    public $phone;
    public $fax;
    public $add_phone_1;
    public $add_phone_2;
    public $mail;
    public $file;
    public $gps_point;
    public $gps_center;
    public $gps_zoom;
}