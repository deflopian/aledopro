<?php
namespace Developers;

use Developers\Model\ProdToProdTable;
use Developers\Model\ProdToProjTable;
use Developers\Model\DeveloperImgTable;
use Developers\Model\DeveloperMemberTable;
use Developers\Model\DeveloperRubricTable;
use Developers\Model\DeveloperTable;
use Developers\Model\ProjToProjTable;

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
                'DevelopersTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new DeveloperTable($dbAdapter);
                    return $table;
                },
                'DeveloperRubricTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new DeveloperRubricTable($dbAdapter);
                    return $table;
                },
                'DevelopersImgTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new DeveloperImgTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
