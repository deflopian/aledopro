<?php
namespace Api\Service;

use Application\Model\PageInfo;
use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Catalog\Controller\BaseController;
use Catalog\Controller\CatalogController;
use Catalog\Model\DopProdGroup;
use Catalog\Model\FilterField;
use Catalog\Model\ProductInMarket;
use Catalog\Model\ProductParam;
use Catalog\Service\CatalogService;
use Documents\Model\Document;
use Zend\Di\ServiceLocatorInterface;
use Zend\Validator\File\ExcludeMimeType;
use Zend\View\Model\ViewModel;

use Catalog\Controller\AdminController;
use Catalog\Model\PopularSeries;
use Catalog\Model\Section;
use Catalog\Model\SeriesDoc;
use Catalog\Model\SubSection;
use Catalog\Model\Series;
use Catalog\Model\Product;

class EntityService {
    /**
     * @param $serviceLocator \Zend\ServiceManager\ServiceLocatorInterface
     * @param $entity SampleModel
     * @param $type integer
     * @return SampleModel|false
     */
    public static function saveEntityByType($serviceLocator, $entity, $type)
    {
        $lastId = $serviceLocator->get(CatalogService::$tables[$type])->save($entity);

        return $lastId;
    }
    /**
     * @param $serviceLocator \Zend\ServiceManager\ServiceLocatorInterface
     * @param $entity SampleModel
     * @param $type integer
     * @return SampleModel|false
     */
    public static function getEntityByType($serviceLocator, $id, $type)
    {
        $entity = $serviceLocator->get(CatalogService::$tables[$type])->find($id);

        if(isset($entity)){
            return $entity;
        }

        return false;
    }
}