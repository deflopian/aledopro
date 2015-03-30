<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:04
 */
namespace Reports;

use Reports\Model\ReportItemsTable;
use Reports\Model\ReportsTable;
use Zend\ServiceManager\ServiceManager;

class Module
{
    public function getAutoloaderConfig() {
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
                'ReportsTable' =>  function($sm) {
                        /** @var $sm ServiceManager  */
                        $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                        $table     = new ReportsTable($dbAdapter);
                        return $table;
                    },
                'ReportItemsTable' =>  function($sm) {
                        /** @var $sm ServiceManager  */
                        $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                        $table     = new ReportItemsTable($dbAdapter);
                        return $table;
                    }
            ),
        );
    }
}