<?php
namespace Catalog;

use Catalog\Model\DiscountTable;
use Catalog\Model\DopProdGroupTable;
use Catalog\Model\DopProdTable;
use Catalog\Model\EqualParamsTable;
use Catalog\Model\FilterFieldTable;
use Catalog\Model\FilterParamTable;
use Catalog\Model\LinkToLink;
use Catalog\Model\LinkToLinkTable;
use Catalog\Model\ParamToSeriesTable;
use Catalog\Model\PopularSeriesTable;
use Catalog\Model\ProductInMarketTable;
use Catalog\Model\ProductMainParamsTable;
use Catalog\Model\SectionTable;
use Catalog\Model\SeriesDimTable;
use Catalog\Model\SeriesDocTable;
use Catalog\Model\SeriesImgTable;
use Catalog\Model\SeriesParamsTable;
use Catalog\Model\StoSTable;
use Catalog\Model\SubSectionTable;
use Catalog\Model\SeriesTable;
use Catalog\Model\ProductTable;
use Catalog\Model\ProductParamsTable;
use Terms\Model\Terms;
use Terms\Model\TermsTable;

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
                'Catalog\Model\SectionTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SectionTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\Discount' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new DiscountTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\SubSectionTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SubSectionTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\SeriesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SeriesTable($dbAdapter);
                    return $table;
                },
                'SeriesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SeriesTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\ProductTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProductTable($dbAdapter);
                    return $table;
                },
                'ProductsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProductTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\ProductParamsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new ProductParamsTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\SeriesImgTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SeriesImgTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\StoSTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new StoSTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\LinkToLinkTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new LinkToLinkTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\SeriesDocTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SeriesDocTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\SeriesDimTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new SeriesDimTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\PopularSeriesTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table     = new PopularSeriesTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\SeriesParamsTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new SeriesParamsTable($dbAdapter);
                    return $table;
                },
                'FilterFieldTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new FilterFieldTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\FilterFieldTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new FilterFieldTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\FilterParamTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new FilterParamTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\ParamToSeriesTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new ParamToSeriesTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\DopProdGroupTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new DopProdGroupTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\DopProdTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new DopProdTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\ProductInMarketTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new ProductInMarketTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\EqualParamsTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new EqualParamsTable($dbAdapter);
                    return $table;
                },
                'Catalog\Model\ProductMainParamsTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new ProductMainParamsTable($dbAdapter);
                    return $table;
                },
                'Terms\Model\Terms' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new TermsTable($dbAdapter);
                    return $table;
                },
            ),
        );
    }
}
