<?php
namespace Application\Controller;

use Application\Model\ShowRoom;

class ShowroomAdminController extends SampleAdminController
{
    protected $entityName = 'Application\Model\ShowRoom';
    protected $imgFields = array("img");
}