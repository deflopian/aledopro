<?php
namespace Solutions;

use Solutions\Model\ProdToSolutionTable;
use Solutions\Model\ProjToSolutionTable;
use Solutions\Model\SolutionTable;

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
                'SolutionsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SolutionTable($dbAdapter);
                    return $table;
                },
                'ProdToSolutionTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProdToSolutionTable($dbAdapter);
                    return $table;
                },
                'ProjToSolutionTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new ProjToSolutionTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
