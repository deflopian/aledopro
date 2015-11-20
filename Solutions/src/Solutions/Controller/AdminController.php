<?php
namespace Solutions\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Solutions\Model\ProdToProd;
use Solutions\Model\ProdToProj;
use Solutions\Model\ProdToSolution;
use Solutions\Model\Project;
use Solutions\Model\ProjectImg;
use Solutions\Model\ProjToProj;
use Solutions\Model\ProjToSolution;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Solutions\Model\Solution';
    private $projTable ='ProjectsTable';
    private $relProdsTable ='ProdToSolutionTable';
    private $relProjsTable ='ProjToSolutionTable';


    public function viewAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$sl = $this->getServiceLocator();
        $viewHelper = $sl->get('viewhelpermanager');
        $viewHelper->get('headscript')->prependFile('/js/adminProject.js');
        $this->imgFields = array('img', 'preview', 'light_img', 'compare_img_1', 'compare_img_2', 'diagram_1', 'diagram_2', 'diagram_3');
        $return = parent::viewAction();

        if(is_array($return)){
            $id = (int) $this->params()->fromRoute('id', 0);

//            $prodTable = $sl->get('Catalog\Model\ProductTable');
//            $allProds = ApplicationService::makeIdArrayFromObjectArray($prodTable->fetchAll());
//            $data = CatalogService::getSeriesAndTags($allProds); // там просто сортировка, переименовывать лень
//            $return['tags'] = \Zend\Json\Json::encode($data['tags']);
//
//            $projTags = $sl->get($this->projTable)->fetchAll();
//            $data = CatalogService::getSeriesAndTags($projTags); // там просто сортировка, переименовывать лень
//            $return['projTags'] = \Zend\Json\Json::encode($data['tags']);
//
//            $relatedProdsIds = $sl->get($this->relProdsTable)->fetchByCond('solution_id', $id);
//            $relatedProds = array();
//            foreach($relatedProdsIds as $pts){
//                $relatedProds[] = $allProds[$pts->prod_id];
//            }
//            $return['relatedProds'] = $relatedProds;
//
//            $relatedProjIds = $sl->get($this->relProjsTable)->fetchByCond('solution_id', $id);
//            $relatedProjs = array();
//            foreach($relatedProjIds as $pjts){
//                $relatedProjs[] = $sl->get($this->projTable)->find($pjts->proj_id);
//            }
//            $return['relatedProjs'] = $relatedProjs;
            $seoData = $sl->get('SeoDataTable')->find( SeoService::SOLUTIONS, $id );
            $return['seoData'] = $seoData;
//            $return['links'] = LinkToLinkMapper::getInstance($sl)->fetchAll($id, \Catalog\Controller\AdminController::SOLUTION_TABLE);
        }

        return $return;
    }

    public function saveImgAction()
    {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $return['success'] = 0;

            if($id){
                $data = $this->getRequest()->getFiles()->toArray();
                $table = $this->getServiceLocator()->get('SolutionsTable');

                $filename = $data['image']['name'];
                $adapter = new \Zend\File\Transfer\Adapter\Http();
                $adapter->setDestination($this->getFoler('/images/solutions'));

                if($adapter->receive($filename)){
                    $entity = $table->find($id);
                    if ($type == 'round_image') {
                        $url = $entity->round_image;
                    } else {
                        $url = $entity->img;
                    }

                    if($url){
                        if (file_exists('/images/solutions/'.$url)) {
                            $this->unlinkFile('/images/solutions/'.$url);
                        }
                    }

                    if ($type == 'round_image') {
                        $entity->round_image = $adapter->getFileName(null, false);
                    } else {
                        $entity->img = $adapter->getFileName(null, false);
                    }

                    $table->save($entity);

                    $return['success'] = 1;
                    $return['imgs'] = array(
                        array(
                            'url' => $url,
                        )
                    );
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('zfcadmin');
    }

    public function saveTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $solutionId = $request->getPost('id', false);
            $prodIds = $request->getPost('tagitIds', false);
            $success = 0;

            if ($solutionId && $prodIds) {
                $table = $this->getServiceLocator()->get($this->relProdsTable);

                foreach(explode(',', $prodIds) as $pid){
                    $ptop = new ProdToSolution();
                    $ptop->exchangeArray(array(
                        'prod_id' => $pid,
                        'solution_id'  => $solutionId,
                    ));
                    $table->save($ptop);
                }
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/project');
    }

    public function saveRelProjAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $solutionId = $request->getPost('id', false);
            $prodIds = $request->getPost('tagitIds', false);
            $success = 0;

            if ($solutionId && $prodIds) {
                $table = $this->getServiceLocator()->get($this->relProjsTable);

                foreach(explode(',', $prodIds) as $pid){
                    $ptop = new ProjToSolution();
                    $ptop->exchangeArray(array(
                        'proj_id'  => $pid,
                        'solution_id' => $solutionId,
                    ));
                    $table->save($ptop);
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/project');
    }

    public function removeParentTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $productId = $request->getPost('id', false);
            $solId = $request->getPost('parentId', false);
            $success = 0;

            if ($solId && $productId) {
                $table = $this->getServiceLocator()->get($this->relProdsTable);
                $table->del(array('solution_id' => $solId, 'prod_id' => $productId));
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/solutions');
    }

    public function removeRelProjAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $projId = $request->getPost('id', false);
            $solId = $request->getPost('parentId', false);
            $success = 0;

            if ($solId && $projId) {
                $table = $this->getServiceLocator()->get($this->relProjsTable);
                $table->del(array('solution_id' => $solId, 'proj_id' => $projId));
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/solutions');
    }
}