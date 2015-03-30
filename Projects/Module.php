<?php
namespace Projects;

use Projects\Model\ProdToProdTable;
use Projects\Model\ProdToProjTable;
use Projects\Model\ProjectImgTable;
use Projects\Model\ProjectMemberTable;
use Projects\Model\ProjectRubricTable;
use Projects\Model\ProjectTable;
use Projects\Model\ProjToProjTable;

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
                'ProjectsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProjectTable($dbAdapter);
                    return $table;
                },
                'ProjectRubricTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProjectRubricTable($dbAdapter);
                    return $table;
                },
                'ProjectsImgTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProjectImgTable($dbAdapter);
                    return $table;
                },
                'ProjectsMemberTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProjectMemberTable($dbAdapter);
                    return $table;
                },
                'ProdToProjTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProdToProjTable($dbAdapter);
                    return $table;
                },
                'ProjToProjTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new ProjToProjTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
