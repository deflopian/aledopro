<?php
namespace Offers\Controller;

use Catalog\Mapper\CatalogMapper;
use Info\Service\SeoService;

use Zend\Mvc\Controller\AbstractActionController;

class OffersController extends AbstractActionController
{
    protected $pageInfoType = SeoService::OFFERS;

    public function indexAction()
    {
        $sl = $this->getServiceLocator();
        $offers = $sl->get('OffersTable')->fetchAll();

        $relatedProdsIds = $sl->get('OfferContentTable')->fetchAll();
        $prodTable = $sl->get('Catalog/Model/ProductTable');
        $relatedProds = array();
        if($relatedProdsIds){
            foreach($relatedProdsIds as $ptp){
                $relatedProds[$ptp->offer_id][] = $prodTable->find($ptp->product_id);
            }
        }
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::OFFERS, 1 );

        $this->layout()->setVariable('pageTitle', 'Спецпредложения');
        $this->layout()->setVariable('seoData', $seoData);


        return array(
            'seoData' => $seoData,
            'offers' => $offers,
            'relProds' => $relatedProds,
        );
    }
}