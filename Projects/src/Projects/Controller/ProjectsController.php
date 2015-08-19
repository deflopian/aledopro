<?php
namespace Projects\Controller;

use Catalog\Controller\AdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Projects\Model\Project;
use Zend\Mvc\Controller\AbstractActionController;
use Projects\Model\ProjectTable;
use Projects\Model\ProjectImgTable;
use Projects\Model\ProjectMemberTable;
use Projects\Model\ProdToProjTable;
use Zend\View\Model\ViewModel;

class ProjectsController extends AbstractActionController
{
    private $projectTable;
    private $imgTable;
    private $memberTable;
    private $prodtoprojTable;
    protected $pageInfoType = SeoService::PROJECTS;

    public function viewAction() {
        $sl = $this->getServiceLocator();

        $id = intval($this->params()->fromRoute('id', 0));
//        if ($id == 43) {
//            return $this->redirect()->toRoute('projects');
//        }
        /** @var Project $project */
        $project = $this->getProjectTable()->find($id);

        $imgs = $this->getImgTable()->fetchByCond('parent_id', $id, 'order asc');
        $members = $this->getMemberTable()->fetchByCond('parent_id', $id, 'order asc');
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::PROJECTS, $id );
        $relatedSeriesIds = $this->getProdToProjTable()->fetchByCond('project_id', $id, 'order asc');
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
        $projects = $this->getServiceLocator()->get('ProjectsTable')->fetchAll('order asc');
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($projects as $pkey => &$one) {
            if ($one->id == $project->id) {
                unset($projects[$pkey]);
                continue;
            }
            if ($one->preview) {
                $file = $fileTable->find($one->preview);
                if ($file) {
                    $one->previewName = $file->name;
                }
            }
        }

        $nextId = CatalogService::getNextId($id, $arrayIds);
        $prevId = CatalogService::getPrevId($id, $arrayIds);

        $nextProd = $this->getProjectTable()->find($nextId);
        $prevProd = $this->getProjectTable()->find($prevId);

        $relatedProjIds = $sl->get('ProjToProjTable')->find($id);
        $relatedProjects = array();

        foreach($relatedProjIds as $sid){
            $relatedProjects[] = $this->getProjectTable()->find($sid);
        }
        $this->layout()->pageTitle = $project->title;
        $this->layout()->breadCrumbs  = array(
            array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            array('link'=> $this->url()->fromRoute('projects'), 'text'=>ucfirst('Проекты'))
        );
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setVariables(array(
                'project'   => $project,
                'imgs'      => $imgs,
                'members'   => $members,
                'relatedSeries'   => $relatedSeries,
                'pageTitle' => $project->title,
                'breadCrumbs'  => array(
                    array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
                    array('link'=> $this->url()->fromRoute('projects'), 'text'=>ucfirst('Проекты')),
                ),
                'nextProd' => $nextProd,
                'prevProd' => $prevProd,
                'relatedProjects' => $relatedProjects,
                'otherProjects' => $projects,
                'seoData' => $seoData,
                'sl'        => $sl,
                'links' => LinkToLinkMapper::getInstance($sl)->fetchAll($id, \Catalog\Controller\AdminController::PROJECT_TABLE)
            ));
        return $htmlViewPart;
    }



    public function indexAction()
    {
        $groupId = intval($this->params()->fromRoute('id', 1));

        $rubric = $this->getServiceLocator()->get('ProjectRubricTable')->find($groupId);

        if ($rubric) {
            $projects = $this->getServiceLocator()->get('ProjectsTable')->fetchByCond('rubric_id', $groupId, 'order asc');


        } else {
            return $this->redirect()->toRoute('home');
        }

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::PROJECTS, 1 );

        $arrayIds = array();
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($projects as &$one) {
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
            'projects' => $projects,
            'rubric' => $rubric,
            'parentUrl'     => '/projects/',
            'bid'           =>  $groupId,
        );
    }

    /**
     * @return ProjectTable array|object
     */
    public function getProjectTable()
    {
        if (!$this->projectTable) {
            $sm = $this->getServiceLocator();
            $this->projectTable = $sm->get('ProjectsTable');
        }
        return $this->projectTable;
    }

    /**
     * @return ProjectImgTable array|object
     */
    public function getImgTable()
    {
        if (!$this->imgTable) {
            $sm = $this->getServiceLocator();
            $this->imgTable = $sm->get('ProjectsImgTable');
        }
        return $this->imgTable;
    }

    /**
     * @return ProjectMemberTable array|object
     */
    public function getMemberTable()
    {
        if (!$this->memberTable) {
            $sm = $this->getServiceLocator();
            $this->memberTable = $sm->get('ProjectsMemberTable');
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