<?php
namespace Developers\Model;

use Application\Model\SampleModel;

class DeveloperMember extends SampleModel
{
    public $id;
    public $parent_id;
    public $title;
    public $role;
    public $link;
    public $link_text;
    public $order;
}