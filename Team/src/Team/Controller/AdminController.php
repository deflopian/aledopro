<?php
namespace Team\Controller;

use Application\Controller\SampleAdminController;
use Info\Service\SeoService;
use Team\Model\Worker;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Team\Model\Worker';

    public function indexAction()
    {
        $this->imgFields = array('img');
        $return = parent::indexAction();



        return $return;
    }
    public function viewAction()
    {
        $this->imgFields = array('img');
        $return = parent::viewAction();



        return $return;
    }
}