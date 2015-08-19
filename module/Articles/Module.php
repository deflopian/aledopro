<?php
namespace Articles;

use Articles\Model\StoATable;
use Articles\Model\ArticleTable;

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
                'ArticlesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ArticleTable($dbAdapter);
                    return $table;
                },
                'SeriesToArticlesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new StoATable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
