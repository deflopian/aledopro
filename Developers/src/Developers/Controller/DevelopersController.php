<?php
namespace Developers\Controller;

use Catalog\Controller\AdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Service\CatalogService;
use Developers\Model\Developer;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Developers\Model\DeveloperTable;
use Developers\Model\DeveloperImgTable;
use Developers\Model\DeveloperMemberTable;
use Developers\Model\ProdToProjTable;
use Zend\View\Model\ViewModel;

class DevelopersController extends AbstractActionController
{
    private $developerTable;
    private $imgTable;
    private $memberTable;
    private $prodtoprojTable;
    protected $pageInfoType = SeoService::PROJECTS;

    public function viewAction() {
        $sl = $this->getServiceLocator();

        $id = intval($this->params()->fromRoute('id', 0));
//        if ($id == 43) {
//            return $this->redirect()->toRoute('developers');
//        }
        /** @var Developer $developer */
        $developer = $this->getDeveloperTable()->find($id);

        $imgs = $this->getImgTable()->fetchByCond('parent_id', $id, 'order asc');
        $members = $this->getMemberTable()->fetchByCond('parent_id', $id, 'order asc');
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::PROJECTS, $id );
        $relatedSeriesIds = $this->getProdToProjTable()->fetchByCond('developer_id', $id, 'order asc');
        $relatedSeries = array();

        $seriesTable = $sl->get('Catalog/Model/SeriesTable');
        $productsTable = $sl->get('Catalog/Model/ProductTable');
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach($relatedSeriesIds as $ptp){
            if ($ptp->product_type == AdminController::SERIES_TABLE) {
                $series = $seriesTable->find($ptp->product_id);
                if ($series->preview) {
                    $file = $fileTable->find($series->preview);
                    if ($file) {
                        $series->previewName = $file->name;
                    }
                }
                $relatedSeries[] = $series;
            } elseif ($ptp->product_type == AdminController::PRODUCT_TABLE) {
                $prod = $productsTable->find($ptp->product_id);
                if ($prod) {
                    $series = $seriesTable->find($prod->series_id);
                    if ($series) {
                        if ($series->preview) {
                            $file = $fileTable->find($series->preview);
                            if ($file) {
                                $series->previewName = $file->name;
                            }
                            $prod->previewName = $series->previewName;
                        } else {
                            $prod->img = $series->img;
                        }
                        $prod->isProduct = true;
                        $relatedSeries[] = $prod;
                    }
                }

            }

        }

        $arrayIds = array();
        $developers = $this->getServiceLocator()->get('DevelopersTable')->fetchAll('order asc');
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($developers as &$one) {
            if ($one->preview) {
                $file = $fileTable->find($one->preview);
                if ($file) {
                    $one->previewName = $file->name;
                }
            }
            $arrayIds[] = $one->id;
        }

        $nextId = CatalogService::getNextId($id, $arrayIds);
        $prevId = CatalogService::getPrevId($id, $arrayIds);

        $nextProd = $this->getDeveloperTable()->find($nextId);
        $prevProd = $this->getDeveloperTable()->find($prevId);

        $relatedProjIds = $sl->get('ProjToProjTable')->find($id);
        $relatedDevelopers = array();

        foreach($relatedProjIds as $sid){
            $relatedDevelopers[] = $this->getDeveloperTable()->find($sid);
        }
        $this->layout()->pageTitle = $developer->title;
        $this->layout()->breadCrumbs  = array(
            array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            array('link'=> $this->url()->fromRoute('developers'), 'text'=>ucfirst('Проекты'))
        );
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setVariables(array(
                'developer'   => $developer,
                'imgs'      => $imgs,
                'members'   => $members,
                'relatedSeries'   => $relatedSeries,
                'pageTitle' => $developer->title,
                'breadCrumbs'  => array(
                    array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
                    array('link'=> $this->url()->fromRoute('developers'), 'text'=>ucfirst('Проекты')),
                ),
                'nextProd' => $nextProd,
                'prevProd' => $prevProd,
                'relatedDevelopers' => $relatedDevelopers,
                'seoData' => $seoData,
                'sl'        => $sl,
                'links' => LinkToLinkMapper::getInstance($sl)->fetchAll($id, \Catalog\Controller\AdminController::PROJECT_TABLE)
            ));
        return $htmlViewPart;
    }



    public function indexAction()
    {
        $groupId = intval($this->params()->fromRoute('id', 1));

        $rubric = $this->getServiceLocator()->get('DeveloperRubricTable')->find($groupId);

        if ($rubric) {
            $developers = $this->getServiceLocator()->get('DevelopersTable')->fetchByCond('rubric_id', $groupId, 'order asc');


        } else {
            return $this->redirect()->toRoute('home');
        }

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::PROJECTS, 1 );

        $arrayIds = array();
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($developers as &$one) {
            if ($one->preview) {
                $file = $fileTable->find($one->preview);
                if ($file) {
                    $one->previewName = $file->name;
                }
            }
            $arrayIds[] = $one->id;
        }


        $this->layout()->pageTitle = 'Проекты';
        $this->layout()->seoData = $seoData;

        return array(
            'seoData' => $seoData,
            'pageTitle' => 'Проекты',
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            ),
            'developers' => $developers,
            'rubric' => $rubric,
            'parentUrl'     => '/developers/',
            'bid'           =>  $groupId,
        );
    }

    /**
     * @return DeveloperTable array|object
     */
    public function getDeveloperTable()
    {
        if (!$this->developerTable) {
            $sm = $this->getServiceLocator();
            $this->developerTable = $sm->get('DevelopersTable');
        }
        return $this->developerTable;
    }

    /**
     * @return DeveloperImgTable array|object
     */
    public function getImgTable()
    {
        if (!$this->imgTable) {
            $sm = $this->getServiceLocator();
            $this->imgTable = $sm->get('DevelopersImgTable');
        }
        return $this->imgTable;
    }

    /**
     * @return DeveloperMemberTable array|object
     */
    public function getMemberTable()
    {
        if (!$this->memberTable) {
            $sm = $this->getServiceLocator();
            $this->memberTable = $sm->get('DevelopersMemberTable');
        }
        return $this->memberTable;
    }

    /**
     * @return ProdToProjTable array|object
     */
    public function getProdToProjTable()
    {
        if (!$this->prodtoprojTable) {
            $sm = $this->getServiceLocator();
            $this->prodtoprojTable = $sm->get('ProdToProjTable');
        }
        return $this->prodtoprojTable;
    }
}