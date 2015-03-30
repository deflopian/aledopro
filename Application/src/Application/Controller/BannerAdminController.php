<?php
namespace Application\Controller;

use Application\Model\BannerImg;

class BannerAdminController extends SampleAdminController
{
    protected $entityName = 'Application\Model\BannerImg';
    protected $imgFields = array("img");
}