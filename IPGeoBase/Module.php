<?php
namespace IPGeoBase;

use IPGeoBase\Model\GeoBannerTable;
use IPGeoBase\Model\ProdToProdTable;
use IPGeoBase\Model\ProdToProjTable;
use IPGeoBase\Model\DeveloperImgTable;
use IPGeoBase\Model\DeveloperMemberTable;
use IPGeoBase\Model\DeveloperRubricTable;
use IPGeoBase\Model\DeveloperTable;
use IPGeoBase\Model\ProjToProjTable;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'GeoBannersTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new GeoBannerTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
