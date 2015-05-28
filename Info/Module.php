<?php
namespace Info;

use Info\Model\AboutTable;
use Info\Model\DeliveryTable;
use Info\Model\FilesTable;
use Info\Model\GuaranteeTable;
use Info\Model\InfoTable;
use Info\Model\JobTable;
use Info\Model\PartnerRequestTable;
use Info\Model\PartnersTable;
use Info\Model\Pluses;
use Info\Model\PlusesTable;
use Info\Model\SeoDataTable;
use Info\Model\ServicesTable;

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
                'InfoTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new InfoTable($dbAdapter);
                    return $table;
                },
                'AboutTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new AboutTable($dbAdapter);
                    return $table;
                },
                'AboutsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new AboutTable($dbAdapter);
                    return $table;
                },
                'PartnersTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new PartnersTable($dbAdapter);
                    return $table;
                },
                'Partnerform' =>  function($sm) {
                    $form = new Form\PartnerForm(null);
                    return $form;
                },
                'InfoServicesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ServicesTable($dbAdapter);
                    return $table;
                },
                'PlusesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new PlusesTable($dbAdapter);
                    return $table;
                },
                'JobsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new JobTable($dbAdapter);
                    return $table;
                },
                'InfoFilesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new FilesTable($dbAdapter);
                    return $table;
                },
                'GuaranteeTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new GuaranteeTable($dbAdapter);
                    return $table;
                },
                'DeliveryTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new DeliveryTable($dbAdapter);
                    return $table;
                },
                'SeoDataTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SeoDataTable($dbAdapter);
                    return $table;
                },
                'PartnerRequestTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new PartnerRequestTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
