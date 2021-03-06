<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:31
 */

namespace Commercials\Controller;



use Application\Controller\SampleAdminController;
use Catalog\Mapper\CatalogMapper;
use Commercials\Service\CommercialService;
use Commercials\Config\CommercialConfig;
use Commercials\Mapper\CommercialMapper;
use Commercials\Mapper\CommercialRoomMapper;

class AdminController extends SampleAdminController {
    protected $entityName = 'Commercials\Model\Commercial';

    public function viewAction()
    {
        return $this->redirect()->toRoute('zfcadmin'); // stop here!

		$sl = $this->getServiceLocator();
        $commercialMapper = CommercialMapper::getInstance($sl);
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }
        $entity = $commercialMapper->get($id);
        if (!$entity) {
            return $this->redirect()->toRoute('zfcadmin/' . $this->url);
        }

        $commercialJson = \Zend\Json\Json::encode($entity);
        if ($entity->rooms) {
            $roomsJson = \Zend\Json\Json::encode($entity->rooms);
        } else {
            $roomsJson = \Zend\Json\Json::encode(array());
        }

//        $return['entityJson'] = $entityJson;

        return array(
            'entity' => $entity,
            'commercialJson' => $commercialJson,
            'roomsJson' => $roomsJson
        );
    }

    public function exportFileAction()
    {
        return $this->redirect()->toRoute('zfcadmin'); // stop here!

        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }
        $commercialMapper = CommercialMapper::getInstance($this->getServiceLocator());

        $commercial = $commercialMapper->get($id, true, true, true);
        CommercialService::makeCommercialXls($commercial, "Vadim Bannov");

        return $this->getResponse()->setStatusCode(200);
    }

    public function viewRoomAction()
    {
        return $this->redirect()->toRoute('zfcadmin'); // stop here!

        $sl = $this->getServiceLocator();
        $commercialRoomMapper = CommercialRoomMapper::getInstance($sl);
        $commercialMapper = CommercialMapper::getInstance($sl);
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }
        $entity = $commercialRoomMapper->get($id, true);
        if (!$entity) {
            return $this->redirect()->toRoute('zfcadmin/' . $this->url);
        }

        $commercial = $commercialMapper->get($entity->commercial_id);

        $commercialRoomJson = \Zend\Json\Json::encode($entity);
        $linkedIds = array();
        $lindedProdsByProdId = array();
        $linkedCounts = array();
        if ($entity->prods) {
            foreach ($entity->prods as $prod) {
                $linkedIds[] = $prod->product_id;
                $lindedProdsByProdId[$prod->product_id] = $prod->id;
                if ($prod->count || $prod->count === 0 || $prod->count === "0") {
                    $linkedCounts[$prod->product_id] = $prod->count;
                } else {
                    $linkedCounts[$prod->product_id] = 1;
                }
            }
        }

        $cm = CatalogMapper::getInstance($sl);
        $sections = $cm->fetchAllSections();
        $subsections = $cm->fetchAllSubsections(true);
        $series = $cm->fetchAllSeries(true);
        $products = $cm->fetchAllProducts(true);
        $treeDateByLvl = array();
        $treeHierarchy = array();
        foreach ($sections as $section) {
            $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id] = array('title' => $section->title, 'is_commercial' => 0);
            $treeHierarchy[$section->id] = array();
        }
        foreach ($subsections as $subsection) {
            if (isset($treeHierarchy[$subsection->section_id])) {
                $treeHierarchy[$subsection->section_id][$subsection->id] = array();
                $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id] = array('title' => $subsection->title, 'parentId' => $subsection->section_id, 'is_commercial' => 0);

            }
        }
        foreach ($series as $oneser) {

            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$oneser->subsection_id])) {
                    $treeHierarchy[$sectionId][$oneser->subsection_id][$oneser->id] = array();

                    $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id] = array('title' => $oneser->title, 'parentId' => $oneser->subsection_id, 'is_commercial' => 0);

                }
            }


        }
        foreach ($products as $product) {
            $series = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
            $subsectionId = $series['parentId'];
            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsectionId];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$subsectionId][$product->series_id])) {
                    $treeHierarchy[$sectionId][$subsectionId][$product->series_id][$product->id] = $product->id;
                    $treeDateByLvl[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id] = array('id' => $product->id, 'title' => $product->title, 'parentId' => $product->series_id, 'is_commercial' => in_array($product->id, $linkedIds), 'commercial_count' => $linkedCounts[$product->id]);
                    if (in_array($product->id, $linkedIds)) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['shown'] = true;
                        $prevSer = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']]['shown'] = true;
                        $prevSS = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$prevSS['parentId']]['shown'] = true;
                    }
                }
            }
        }

        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDateByLvl);
        $treeHierarchyJson = \Zend\Json\Json::encode($treeHierarchy);
        $commercials = array();
        foreach ($treeDateByLvl[4] as $prodId => $item) {
            if ($item['is_commercial']) {
                $commercials[$lindedProdsByProdId[$prodId]] = $item;
            }
        }

        $commercialsJson = \Zend\Json\Json::encode($commercials);

        $return['treeDateByLvlJson'] = $treeDateByLvlJson;

        $return['treeHierarchyJson'] = $treeHierarchyJson;
        $return['commercialsJson'] = $commercialsJson;



//        $return['entityJson'] = $entityJson;

        return array(
            'entity' => $entity,
            'commercial' => $commercial,
            'roomJson' => $commercialRoomJson,
            'treeDateByLvlJson' => $treeDateByLvlJson,
            'treeHierarchyJson' => $treeHierarchyJson,
            'commercialsJson' => $commercialsJson,
        );
    }



    public function indexAction()
    {
        return $this->redirect()->toRoute('zfcadmin'); // stop here!

        $sl = $this->getServiceLocator();
        $commercialMapper = CommercialMapper::getInstance($sl);
        $commercials = $commercialMapper->getList();
        $commercialsJson = \Zend\Json\Json::encode($commercials);

        return array(
            'entities' => $commercials,
            'commercialsJson' => $commercialsJson
        );
    }
} 