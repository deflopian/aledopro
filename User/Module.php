<?php
namespace User;

use User\Model\ManagerToUserTable;
use User\Model\RoleLinkerTable;
use User\Model\UserHistoryTable;
use User\Model\UserRoleTable;
use User\Model\UserTable;
use Zend\Mvc\ModuleRouteListener;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'UserTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new UserTable($dbAdapter);
                    return $table;
                },
                'UserRoleTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new UserRoleTable($dbAdapter);
                    return $table;
                },
                'UserHistoryTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new UserHistoryTable($dbAdapter);
                    return $table;
                },
                'RoleLinkerTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new RoleLinkerTable($dbAdapter);
                    return $table;
                },
                'ManagerToUserTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ManagerToUserTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
