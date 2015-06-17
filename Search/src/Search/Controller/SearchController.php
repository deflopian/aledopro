<?php
namespace Search\Controller;

use Application\Service\ApplicationService;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractActionController;

class SearchController extends AbstractActionController
{
    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);

        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            $viewHelper = $controller->getServiceLocator()->get('viewhelpermanager');
            $viewHelper->get('headlink')
                ->prependStylesheet('/Content/css/catalog.css');

            $viewHelper->get('headscript')
                ->prependFile('/js/libs/ZeroClipboard.min.js')
                ->prependFile('/js/catalog.js');
        }, 100); // execute before executing action logic
    }

    function explodeX($delimiters, $string)
    {
        $return_array = Array($string); // The array to return
        $d_count = 0;
        while (isset($delimiters[$d_count])) {
            $new_return_array = Array();
            foreach ($return_array as $el_to_split) // Explode all returned elements by the next delimiter
            {
                $put_in_new_return_array = explode($delimiters[$d_count], $el_to_split);
                foreach ($put_in_new_return_array as $substr)
                {
                    $new_return_array[] = $substr;
                }
            }
            $return_array = $new_return_array;
            $d_count++;
        }
        return $return_array;
    }


    public function indexAction()
    {
        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        $search = $request->getQuery('search', false);
        if (!$search) {
            return array();
        }

        $productTable = $this->getServiceLocator()->get('Catalog\Model\ProductTable');
        $seriesTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
        $sectionTable = $this->getServiceLocator()->get('Catalog\Model\SectionTable');
        $subsectionTable = $this->getServiceLocator()->get('Catalog\Model\SubSectionTable');
        $articlesTable = $this->getServiceLocator()->get('ArticlesTable');
        $newsTable = $this->getServiceLocator()->get('NewsTable');
        $projectsTable = $this->getServiceLocator()->get('ProjectsTable');

        $search = trim($search, '+*-/\\{}[]');

        if (is_numeric($search)) {

            $oneProdById = $productTable->find($search);
            if ($oneProdById && ($oneProdById->series_id == 0)) {
                $oneProdById = false;
            }
            /*$oneSeriesById = $seriesTable->find($search);
            $oneSectionById = $sectionTable->find($search);
            $oneSubsectionById = $subsectionTable->find($search);
            $oneArticleById = $articlesTable->find($search);
            $oneNewsById = $newsTable->find($search);
            $oneProjectById = $projectsTable->find($search);*/
        } else {
            $oneProdById = false;
        }

        $escapedQuery = addslashes($search);
        $parsedQuery = $this->explodeX(array('_','-',' '), $escapedQuery);

        $anyChar = '.*';
        $oneChar = '[ -_]?';

        foreach ($parsedQuery as &$parsedPart) {

            $parsedPart = implode($oneChar, str_split($parsedPart));
        }
        $query = $anyChar . implode($anyChar, $parsedQuery) . $anyChar;

        $hiddenSections = $sectionTable->fetchByCond('deleted', '1');
        $hiddenSectionsIds = array();
        foreach ($hiddenSections as $hs) {
            $hiddenSectionsIds[$hs->id] = $hs->id;
        }

        $hiddenSubsections = $subsectionTable->fetchByCond('deleted', '1');
        $inheritHiddenSubsections =$subsectionTable->fetchByCond('section_id', $hiddenSectionsIds);
        $hiddenSubsectionsIds = array();
        foreach ($hiddenSubsections as $hs) {
            $hiddenSubsectionsIds[$hs->id] = $hs->id;
        }
        foreach ($inheritHiddenSubsections as $hs) {
            $hiddenSubsectionsIds[$hs->id] = $hs->id;
        }

        $hiddenSeries = $seriesTable->fetchByCond('deleted', '1');
        $inheritHiddenSeries = $seriesTable->fetchByCond('subsection_id', $hiddenSubsectionsIds);
        $hiddenSeriesIds = array();
        foreach ($hiddenSeries as $hs) {
            $hiddenSeriesIds[$hs->id] = $hs->id;
        }
        foreach ($inheritHiddenSeries as $hs) {
            $hiddenSeriesIds[$hs->id] = $hs->id;
        }

        $specialCondition = ' AND `series_id` != 0 AND `type` != ""';
        foreach ($hiddenSeriesIds as $hid) {
            $specialCondition .= ' AND `series_id` != ' . $hid;
        }

        $resultsByProducts = $productTable->selectLike('title', $query, '*', $specialCondition);
        $fileTable = $this->getServiceLocator()->get('FilesTable');

        $resultsBySeries = $seriesTable->selectLike(array('title', 'visible_title'), $query, '*', ' AND (`deleted` != 1 OR `deleted` IS NULL)');
        $resultsBySections = $sectionTable->selectLike('title', $query, '*', ' AND `deleted` != 1');
        $resultsBySubSections = $subsectionTable->selectLike('title', $query, '*', ' AND `deleted` != 1');
        $resultsByArticles = $articlesTable->selectLike('title', $query);
        $resultsByNews = $newsTable->selectLike('title', $query);
        $resultsByProjects = $projectsTable->selectLike('title', $query, '*', ' AND `rubric_id` = 1');
        foreach ($resultsByProjects as &$one) {
            if ($one->preview) {
                $file = $fileTable->find($one->preview);
                if ($file) {
                    $one->previewName = $file->name;
                }
            }
            $arrayIds[] = $one->id;
        }
        //получаем картинки для продуктов
        $sortedProds = array();
        if ($oneProdById !== false) {
            $resultsByProducts[] = $oneProdById;
        }
        $emptySeries = array();
        if ($resultsBySeries) {

            foreach($resultsBySeries as $serKey => $oneSeries){

                if (empty($oneSeries->subsection_id)) {

                    unset($resultsBySeries[$serKey]);
                } else {
                    if ($oneSeries->preview) {
                        $file = $fileTable->find($oneSeries->preview);
                        if ($file) {
                            $resultsBySeries[$serKey]->previewName = $file->name;
                        }
                    }

                    $ss = $subsectionTable->find($oneSeries->subsection_id);
                    if (!$ss) {
                        unset($resultsBySeries[$serKey]);
                    }
                }
            }
        }
        if($resultsByProducts){
            $sorterSeries = $prodIds = array();
            foreach($resultsByProducts as $prod){
                $sortedProds[$prod->id] = $prod;
                $prodIds[] = $prod->series_id;
            }
            $series = $seriesTable->fetchByCond('id', $prodIds);
            foreach($series as $serKey => $ser){
                if (empty($ser->subsection_id)) {
                    foreach ($sortedProds as $sortedKey => $sortedVal) {
                        if ($sortedVal->series_id == $ser->id) {
                            unset($sortedProds[$sortedKey]);
                        }
                    }
                } else {
                    if ($ser->preview) {

                        $file = $fileTable->find($ser->preview);
                        if ($file) {
                            $ser->previewName = $file->name;
                        }
                    }
                    $sorterSeries[$ser->id] = $ser;
                }
            }
            foreach($sortedProds as &$prod){
                $prod->img = isset($sorterSeries[$prod->series_id]) ? $sorterSeries[$prod->series_id]->img : null;
            }
        }

        $prodsCount = sizeof($sortedProds);
        $seriesCount = sizeof($resultsBySeries);
        $newsCount = sizeof($resultsByNews);
        $articlesCount = sizeof($resultsByArticles);
        $projectsCount = sizeof($resultsByProjects);
        $count = $seriesCount + $prodsCount + $newsCount + $articlesCount + $projectsCount;

        $newsSorted = ApplicationService::sortForThreeArrays($resultsByNews);
        $articlesSorted = ApplicationService::sortForThreeArrays($resultsByArticles);


        $results = array(
            'count' => array(
                'total'      => $count,
                'products'   => $prodsCount,
                'series'     => $seriesCount,
                'news'       => $newsCount,
                'projects'   => $projectsCount,
                'articles'   => $articlesCount,
            ),
            'catalog' => array(
                'sections'    => $resultsBySections,
                'subsections' => $resultsBySubSections,
                'series'      => $resultsBySeries,
                'products'    => $sortedProds,
            ),
            'news'     => $newsSorted,
            'articles' => $articlesSorted,
            'projects' => $resultsByProjects,
            'one_product' => $oneProdById,
            'pageTitle' => $count .' Результатов поиска "'. $search .'"',
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Поиск'))
            ),
        );


        $this->layout()->pageTitle = $count .' Результатов поиска "'. $search .'"';
        return $results;
    }
}