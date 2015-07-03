<?php
namespace Developers\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Developers\Model\ProdToProd;
use Developers\Model\ProdToProj;
use Developers\Model\Developer;
use Developers\Model\DeveloperImg;
use Developers\Model\ProjToProj;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Developers\Model\Developer';
    protected $entityImgName = 'Developers\Model\DeveloperImg';
    protected $memberEntityName = 'Developers\Model\DeveloperMember';
    private $memberTable = 'DevelopersMemberTable';
//    private $stosTable = 'DevelopersMemberTable';
    private $pToPTable = 'ProdToProjTable';
    private $projToProjTable = 'ProjToProjTable';

    public function indexAction()
    {
        $return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::DEVELOPERS, 1 );

        return $return;
    }

    public function viewAction()
    {
        $sl = $this->getServiceLocator();
        $viewHelper = $sl->get('viewhelpermanager');
        $viewHelper->get('headscript')->prependFile('/js/adminDeveloper.js');

        $return = parent::viewAction();

        if(is_array($return)){

            if ($return['entity']->preview) {
                $fileTable = $sl->get('FilesTable');
                $file = $fileTable->find($return['entity']->preview);
                if ($file) {
                    $return['entity']->previewName = $file->name;
                }
            }
            if ($return['entity']->img) {
                $fileTable = $sl->get('FilesTable');
                $file = $fileTable->find($return['entity']->img);
                if ($file) {
                    $return['entity']->imgName = $file->name;
                }
            }

            $id = (int) $this->params()->fromRoute('id', 0);
            $allRubrics = $sl->get('DeveloperRubricTable')->fetchAll('order asc');
            $rubrics = array();
            foreach ($allRubrics as $rubric) {
                $rubrics[$rubric->id] = $rubric->title;
            }
            $return['rubrics'] = $rubrics;
            $return['imgs'] = $sl->get($this->tableImg)->fetchByCond('parent_id', $id, 'order asc');
//            $return['members'] = $sl->get($this->memberTable)->fetchByCond('parent_id', $id, 'order asc');
//            $prodTable = $sl->get('Catalog\Model\ProductTable');
//            /** @var \Catalog\Model\SeriesTable $serTable */
//            $serTable = $sl->get('Catalog\Model\SeriesTable');
//            $allSeries = $serTable->fetchAll();
//            $allProds = ApplicationService::makeIdArrayFromObjectArray($prodTable->fetchAll());
//            $allSeries = ApplicationService::makeIdArrayFromObjectArray($prodTable->fetchAll());
//            $data = CatalogService::getSeriesAndTags($allProds); // там просто сортировка, переименовывать лень
//            $data = CatalogService::getSeriesAndTags($allSeries, 0);
//            $allProds = ApplicationService::makeIdArrayFromObjectArray($sl->get('Catalog\Model\ProductTable')->fetchAll());
//            $data = array_merge_recursive($data, CatalogService::getSeriesAndTags($allProds));

//            $return['tags'] = \Zend\Json\Json::encode($data['tags']);

            $projTags = $sl->get($this->table)->fetchAll();
            foreach($projTags as $i=>$pr){
                if($pr->id == $id){
                    unset($projTags[$i]);
                    break;
                }
            }
            $data = CatalogService::getSeriesAndTags($projTags); // там просто сортировка, переименовывать лень



            $return['projTags'] = \Zend\Json\Json::encode($data['tags']);



//            $relatedSeriesIds = $sl->get($this->pToPTable)->fetchByCond('developer_id', $id, 'order ASC');
//            $relatedSeries = array();
//            $relatedProds = array();
//            foreach($relatedSeriesIds as $sid){
//
//                if ($sid->product_type ==  \Catalog\Controller\AdminController::SERIES_TABLE) {
//                    $prod = $serTable->find($sid->product_id);
//                    $prod->product_type = \Catalog\Controller\AdminController::SERIES_TABLE;
////                    $relatedSeries[] = $serTable->find($sid->product_id);
//                } elseif ($sid->product_type ==  \Catalog\Controller\AdminController::PRODUCT_TABLE) {
//                    $prod = $prodTable->find($sid->product_id);
//                    $prod->product_type = \Catalog\Controller\AdminController::PRODUCT_TABLE;
//                }
//                if ($prod) {
//                    $prod->meta_id = $sid->id;
//                    $relatedProds[] = $prod;
//                }
//
//            }
////            if (!empty($relatedSeriesIdsArray)) {
////                $relatedSeries = $serTable->fetchByCond('id', $relatedSeriesIdsArray);
////            } else {
////                $relatedSeries = array();
////            }
//            $return['relatedSeries'] = $relatedSeries;
//            $return['relatedProds'] = $relatedProds;
//
//            $relatedProjIds = $sl->get($this->projToProjTable)->find($id);
//            $relatedDevelopers = array();
//            foreach($relatedProjIds as $sid){
//                $relatedDevelopers[] = $sl->get($this->table)->find($sid);
//            }
//            $return['relatedDevelopers'] = $relatedDevelopers;
            $seoData = $sl->get('SeoDataTable')->find( SeoService::DEVELOPERS, $id );
            $return['seoData'] = $seoData;
        }

        return $return;
    }

    public function viewMemberAction()
    {
        $this->setData();

        $id = (int) $this->params()->fromRoute('id', 0);
        $member = $this->getServiceLocator()->get($this->memberTable)->find($id);
        if ($member === false) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }

        $developer = $this->getServiceLocator()->get($this->table)->find($member->parent_id);

        return array(
            'member' => $member,
            'developer' => $developer,
        );
    }

    public function addEntityAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $type = $request->getPost('type', false);
            $parentId = $request->getPost('parentId', false);

            $success = 0;

            if ($title) {
                $data = array('title' => $title,);

                $table = $this->getTable($type);
                $eName = $this->getEntityName($type);
                if($parentId){
                    $data['parent_id'] = $parentId;
                }

                $entity = new $eName;
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get($table)->save($entity);

                if($newId){
                    $success = 1;
                }
            }

            $returnArr = array('success' => $success);
            if($success){
                $returnArr['newId'] = $newId;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function delEntityAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id) {
                $table = $this->getTable($type);

                $this->getServiceLocator()->get($table)->del($id);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function changeOrderAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $order = $request->getPost('order', false);
            $isImg = $request->getPost('isImg', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($order) {
                $tableName = $this->getTable($type, $isImg);
                $table = $this->getServiceLocator()->get($tableName);

                foreach($order as $id=>$serialNum){
                    $entity = $table->find($id);
                    if($entity){
                        $entity->order = $serialNum;
                        $table->save($entity);

                        if($isImg && $serialNum == 0){
                            // если меняем порядок картинок
                            // ставим нулевую (первую) картинку, как обложку для серии
                            $projTable = $this->getServiceLocator()->get($this->table);
                            $developer = $projTable->find($entity->parent_id);
                            $developer->img = $entity->url;
                            $projTable->save($developer);
                        }
                    }
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function updateEditableAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['pk']) {
                $pkData = explode('-',$post['pk']);
                $type = false;
                if(sizeof($pkData)>1){ // для проджект мемберов
                    $type = 'prmember';
                    $data['id'] = $pkData[1];
                    $data['parent_id'] = $pkData[0];
                } else {
                    $data['id'] = $pkData[0];
                }
                $data[$post['name']] = $post['value'];

                $table = $this->getTable($type);
                $eName = $this->getEntityName($type);

                $entity = new $eName;
                $entity->exchangeArray($data);

                $this->getServiceLocator()->get($table)->save($entity);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function saveTagitAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $developerId = $request->getPost('id', false);
            $prodIds = $request->getPost('tagitIds', false);
            $success = 0;
            $type = $request->getPost('type', false);
            if($type && $type == 'stos'){
                $table = $this->getServiceLocator()->get($this->pToPTable);

                foreach(explode(',', $prodIds) as $sid){
                    //todome: просто пиздец костыль, из-за того, что на странице нельзя сделать несколько разных тагитов
                    $tagitCatalogType = (substr($sid, 0, 3) == 100 || $sid < 20000) ? \Catalog\Controller\AdminController::SERIES_TABLE : \Catalog\Controller\AdminController::PRODUCT_TABLE;

                    $stos = new ProdToProj();
                    $stos->exchangeArray(array(
                        'developer_id' => $developerId,
                        'product_id' => $sid,
                        'product_type' => $tagitCatalogType,
                    ));
                    $table->save($stos);
                }

            } elseif ($developerId && $prodIds) {
                $table = $this->getServiceLocator()->get($this->pToPTable);

                foreach(explode(',', $prodIds) as $pid){
                    $ptop = new ProdToProj();
                    $ptop->exchangeArray(array(
                        'developer_id' => $developerId,
                        'product_id'  => $pid,
                    ));

                    $table->save($ptop);
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/developer');
    }

    public function saveRelProjAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $developerId = $request->getPost('id', false);
            $prodIds = $request->getPost('tagitIds', false);
            $success = 0;

            if ($developerId && $prodIds) {
                $table = $this->getServiceLocator()->get($this->projToProjTable);

                foreach(explode(',', $prodIds) as $pid){
                    $ptop = new ProjToProj();
                    $ptop->exchangeArray(array(
                        'proj_id_1' => $developerId,
                        'proj_id_2'  => $pid,
                    ));

                    $table->save($ptop);
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/developer');
    }

    public function removeParentTagitAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $productId = $request->getPost('id', false);
            $developerId = $request->getPost('parentId', false);
            $success = 0;

            if ($developerId && $productId) {
                $table = $this->getServiceLocator()->get($this->pToPTable);
                $table->del(array('developer_id' => $developerId, 'product_id' => $productId));
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/developers');
    }

    public function removeRelProjAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $productId = $request->getPost('id', false);
            $developerId = $request->getPost('parentId', false);
            $success = 0;

            if ($developerId && $productId) {
                $table = $this->getServiceLocator()->get($this->projToProjTable);
                $table->del(array('proj_id_1' => $developerId, 'proj_id_2' => $productId));
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/developers');
    }

    private function getTable($type, $isImg = false)
    {
        if($isImg){
            $name = $this->tableImg;
        } else {
            switch ($type) {
                case 'prmember' :
                    $name = $this->memberTable;
                    break;
                case 'ptop' :
                    $name = $this->pToPTable;
                    break;
                default:
                    $name =  $this->table;

            }
//            $name = $type == 'prmember' ? $this->memberTable : $this->table;
        }
        return $name;
    }

    private function getEntityName($type)
    {
        return $type == 'prmember'? $this->memberEntityName : $this->entityName;
    }
}