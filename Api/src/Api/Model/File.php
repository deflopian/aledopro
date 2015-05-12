<?php
namespace Api\Model;

use Application\Model\SampleModel;

class File extends SampleModel
{
    public $id;
    public $name;
    public $type;
    public $real_name;
    public $path;
    public $size;
    public $timestamp;
    public $uid;
}