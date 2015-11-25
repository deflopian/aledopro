<?php
namespace Cart\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Cart\Model\Order;
use Catalog\Service\CatalogService;
use Catalog\Mapper\CatalogMapper;
use Info\Service\SeoService;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Cart\Model\Order';

    public function indexAction()
    {
        $this->setData();
        $sl = $this->getServiceLocator();
        $entities = $sl->get($this->table)->fetchAll('id DESC');

        $users = $sl->get('UserTable')->fetchAll();
        $users = ApplicationService::makeIdArrayFromObjectArray($users, 'user_id');

        $unregUsers = $sl->get('OrderUserTable')->fetchAll();
        $unregUsers = ApplicationService::makeIdArrayFromObjectArray($unregUsers, 'order_id');

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::CART, 1 );

        return array(
            'entities' => $entities,
            'users' => $users,
            'unregUsers' => $unregUsers,
            'seoData' => $seoData,
			'isDomainZoneBy' => ApplicationService::isDomainZone('by')
        );
    }

    public function viewAction()
    {
        $sl = $this->getServiceLocator();
        $return = parent::viewAction();

        if(is_array($return)){
            $user =  $return['entity']->user_id
                ? $sl->get('UserTable')->find($return['entity']->user_id)
                : $sl->get('OrderUserTable')->find($return['entity']->id, 'order_id');
            $return['user'] = $user;

            $relatedProds = $sl->get('ProdToOrderTable')->fetchByCond('order_id', $return['entity']->id);
            $prodTable = $sl->get('Catalog/Model/ProductTable');
            foreach($relatedProds as $i=>$relProd){
				$catalogMapper = CatalogMapper::getInstance($sl);
				list($tree, $type) = $catalogMapper->getParentTree($relProd->product_id);
				
				$priceRequestTable = $sl->get('PriceRequestTable');
				$requests = $priceRequestTable->fetchAllSorted();
		
                $prod = $prodTable->find($relProd->product_id);
                $relatedProds[$i]->title = $prod->title;
                $relatedProds[$i]->price = CatalogService::getTruePrice($prod->price_without_nds, null, $tree, null, 0, $requests, true);
            }
            $return['relatedProds'] = $relatedProds;
        }

        return $return;
    }
}