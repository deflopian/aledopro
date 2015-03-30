<?php
namespace Services;

use Services\Model\ModernisationRequestTable;
use Services\Model\MontajRequestTable;
use Services\Model\ServiceTable;
use Services\Model\ConsultRequestTable;
use Services\Model\ProjRequestTable;
use Services\Model\CalcRequestTable;

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
                'ServicesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ServiceTable($dbAdapter);
                    return $table;
                },
                'CalcRequestTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new CalcRequestTable($dbAdapter);
                    return $table;
                },
                'ProjRequestTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProjRequestTable($dbAdapter);
                    return $table;
                },
                'ConsultRequestTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ConsultRequestTable($dbAdapter);
                    return $table;
                },
                'MontajRequestTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new MontajRequestTable($dbAdapter);
                    return $table;
                },
                'ModernRequestTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ModernisationRequestTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
