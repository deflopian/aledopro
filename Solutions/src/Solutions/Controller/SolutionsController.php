<?php
namespace Solutions\Controller;

use Catalog\Controller\AdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Service\CatalogService;
use Solutions\Model\Solution;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Solutions\Model\SolutionTable;
use Solutions\Model\ProdToSolutionTable;
use Solutions\Model\ProjToSolutionTable;
use Zend\View\Model\ViewModel;

class SolutionsController extends AbstractActionController
{
    private $solutionTable;
    private $prodToSolutionTable;
    private $projToSolutionTable;


    public function indexAction()
    {

        $solutions = $this->getSolutionTable()->fetchAll('order asc');

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::SOLUTIONS, 1 );
        $imgFields = array('img', 'preview');
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($solutions as &$one) {
            foreach ($imgFields as $imgField) {
                if ($one->$imgField) {
                    $file = $fileTable->find($one->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $one->$imgFieldAndName = $file->name;
                    }
                }
            }
        }
        $firstSolution = array_shift($solutions);

        $this->layout()->pageTitle = 'Решения';
        $this->layout()->seoData = $seoData;

        return array(
            'seoData' => $seoData,
            'solutions' => $solutions,
            'parentUrl'     => '/solutions/',
            'firstSolution' => $firstSolution
        );
    }

    public function viewAction() {
        $sl = $this->getServiceLocator();
        $id = intval($this->params()->fromRoute('id', 0));
        /** @var Solution $solution */
        $solution = $this->getSolutionTable()->find($id);

        if (!$solution) {
            /** @var Response $response */
            $response = $this->getResponse();
            return $response->setStatusCode(404);
        }

        $imgFields = array('img', 'preview', 'light_img', 'compare_img_1', 'compare_img_2', 'diagram_1', 'diagram_2', 'diagram_3');

        $fileTable = $sl->get('FilesTable');
        foreach ($imgFields as $imgField) {
            if ($solution->$imgField) {
                $file = $fileTable->find($solution->$imgField);
                if ($file) {
                    $imgFieldAndName = $imgField . "_name";
                    $solution->$imgFieldAndName = $file->name;
                }
            }
        }

//
//        $relatedProdsIds = $this->getProdToSolutionTable()->fetchByCond('solution_id', $id);
//
//        $relatedProds = array();
//        $prodTable = $sl->get('Catalog/Model/ProductTable');
//        $seriesTable = $sl->get('Catalog/Model/SeriesTable');
//        foreach($relatedProdsIds as $ptp){
//            $product = $prodTable->find($ptp->prod_id);
//            $series = $seriesTable->find($product->series_id);
//            if($series){
//                if ($series->preview && isset($series->previewName)) {
//                    $product->img = $series->previewName;
//                } else {
//                    $product->img = $series->img;
//                }
//
//                $relatedProds[] = $product;
//            }
//        }
//
//        $solutions =  $this->getSolutionTable()->fetchAll('order ASC');
//        $solutionIds = array();
//        foreach ($solutions as $one) {
//            $solutionIds[] = $one->id;
//        }
//        $prevId = CatalogService::getPrevId($id, $solutionIds);
//        $nextId = CatalogService::getNextId($id, $solutionIds);
//        $nextSol = $this->getSolutionTable()->find($nextId);
//        $prevSol = $this->getSolutionTable()->find($prevId);
//
//        $relatedProjIds = $this->getProjToSolutionTable()->fetchByCond('solution_id', $id);
//        $relatedProjs = array();
//        foreach($relatedProjIds as $relProj){
//            $relatedProjs[] = $sl->get('ProjectsTable')->find($relProj->proj_id);
//        }
        $this->layout()->pageTitle = $solution->title;
//        $this->layout()->breadCrumbs  = array(
//            array('link'=> '/?p=main-solutions', 'text'=>ucfirst('Решения'))
//        );
        $htmlViewPart = new ViewModel();

        $l2lMapper = LinkToLinkMapper::getInstance($sl);

        $htmlViewPart
            ->setVariables(array(
                'solution'   => $solution,
//                'relatedProds'   => $relatedProds,
//                'nextSol' => $nextSol,
//                'prevSol' => $prevSol,
//                'relatedProjs' => $relatedProjs,
                'links' => $l2lMapper->fetchAll($id,AdminController::SOLUTION_TABLE),
                'sl'        => $sl
            ));
        return $htmlViewPart;
    }


    /**
     * @return SolutionTable array|object
     */
    public function getSolutionTable()
    {
        if (!$this->solutionTable) {
            $sm = $this->getServiceLocator();
            $this->solutionTable = $sm->get('SolutionsTable');
        }
        return $this->solutionTable;
    }

    /**
     * @return ProdToSolutionTable array|object
     */
    public function getProdToSolutionTable()
    {
        if (!$this->prodToSolutionTable) {
            $sm = $this->getServiceLocator();
            $this->prodToSolutionTable = $sm->get('ProdToSolutionTable');
        }
        return $this->prodToSolutionTable;
    }

    /**
     * @return ProjToSolutionTable array|object
     */
    public function getProjToSolutionTable()
    {
        if (!$this->projToSolutionTable) {
            $sm = $this->getServiceLocator();
            $this->projToSolutionTable = $sm->get('ProjToSolutionTable');
        }
        return $this->projToSolutionTable;
    }
}