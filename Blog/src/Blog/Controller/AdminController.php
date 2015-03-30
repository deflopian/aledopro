<?php
namespace Blog\Controller;

use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;

class AdminController extends AbstractActionController
{
    public function indexAction()
    {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::BLOG, 1 );
        return array(
            'seoData' => $seoData
        );
    }
}