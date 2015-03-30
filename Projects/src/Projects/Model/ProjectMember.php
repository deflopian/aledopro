<?php
namespace Projects\Model;

use Application\Model\SampleModel;

class ProjectMember extends SampleModel
{
    public $id;
    public $parent_id;
    public $title;
    public $role;
    public $link;
    public $link_text;
    public $order;
}