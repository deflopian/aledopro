<?php
namespace Application;

use Application\Model\BannerImgTable;
use Application\Model\FooterBlockTable;
use Application\Model\MainPageBlockImageTable;
use Application\Model\MainPageBlockTable;
use Application\Model\PageInfoTable;
use Application\Model\ShowRoomTable;
use Application\Model\UserRoleTable;
use Application\Model\UserTable;
use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\Mvc\ModuleRouteListener;
use Locale;
use Zend\Mvc\MvcEvent;

class Module implements BootstrapListenerInterface
{
    public function onBootstrap(EventInterface $e)
    {
        $translator = $e->getApplication()->getServiceManager()->get('translator');
        $translator->setLocale('ru_RU');
    }

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
                'BannerTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new BannerImgTable($dbAdapter);
                    return $table;
                },
                'ShowRoomTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ShowRoomTable($dbAdapter);
                    return $table;
                },
                'ShowRoomsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ShowRoomTable($dbAdapter);
                    return $table;
                },
                'FooterBlocksTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new FooterBlockTable($dbAdapter);
                    return $table;
                },
                'BannersTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new BannerImgTable($dbAdapter);
                    return $table;
                },
                'MainPageBlocksTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new MainPageBlockTable($dbAdapter);
                    return $table;
                },
                'MainPageBlockImagesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new MainPageBlockImageTable($dbAdapter);
                    return $table;
                },
                'PageInfoTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new PageInfoTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
