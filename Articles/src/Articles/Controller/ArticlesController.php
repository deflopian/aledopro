<?php
namespace Articles\Controller;

use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Articles\Model\Article;
use Articles\Model\ArticleTable;
use Zend\View\Model\ViewModel;

class ArticlesController extends AbstractActionController
{
    private $articleTable;
    protected $pageInfoType = SeoService::BLOG;

    public function viewAction() {
        $sl = $this->getServiceLocator();

        $id = intval($this->params()->fromRoute('id', 0));

        /** @var Article $article */
        $article = $this->getArticleTable()->find($id);

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::ARTICLES, $id );

        $arrayIds = array();
        $articles = $this->getArticleTable()->fetchAll('order asc');
        foreach ($articles as $one) {
            $arrayIds[] = $one->id;
        }

        $nextId = CatalogService::getNextId($id, $arrayIds);
        $prevId = CatalogService::getPrevId($id, $arrayIds);

        $nextProd = $this->getArticleTable()->find($nextId);
        $prevProd = $this->getArticleTable()->find($prevId);

        $relatedSeriesIds = $sl->get('SeriesToArticlesTable')->fetchByCond('article_id',$id);
        $seriesTable = $sl->get('Catalog\Model\SeriesTable');
        $relatedSeries = array();
        foreach($relatedSeriesIds as $rser){
            $relatedSeries[] = $seriesTable->find($rser->series_id);
        }

        $blocksTable = $this->getServiceLocator()->get('ArticleBlocksTable');
        $blocks = $blocksTable->fetchByCond('article_id', $id, 'order ASC');
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($blocks as &$block) {

            foreach (array('img') as $imgField) {
                if ($block->$imgField) {
                    $file = $fileTable->find($block->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $block->$imgFieldAndName = $file->name;
                    }
                }
            }
        }
        $article->blocks = $blocks;


        $this->layout()->pageTitle = $article->title;
        $this->layout()->breadCrumbs  = array(
            array('link'=> $this->url()->fromRoute('blog'), 'text'=>ucfirst('Блог'))
        );
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setVariables(array(
            'article'   => $article,
            'nextProd' => $nextProd,
            'prevProd' => $prevProd,
            'relatedSeries'   => $relatedSeries,
            'seoData' => $seoData,
            'sl'        => $sl
        ));
        return $htmlViewPart;
    }

    public function indexAction()
    {
      //  return $this->redirect()->toRoute('blog');
    }

    public function getPopupContentAction()
    {
        $request = $this->getRequest();
        if (($robot = $this->params()->fromRoute('robot', false)) || $this->getRequest()->isXmlHttpRequest()) {
            if ($robot) {
                $id = $this->params()->fromRoute('id', 0);
                $prevId = $this->params()->fromRoute('prevId', false);
                $nextId = $this->params()->fromRoute('nextId', false);
            } else {
                $id = $request->getPost('id', false);
                $nextId = $request->getPost('nextid', false);
                $prevId = $request->getPost('previd', false);
            }
            $success = 0;
            $content = '';

            if ($id) {
                $article = $this->getArticleTable()->find($id);
                $sl= $this->getServiceLocator();
                $relatedSeriesIds = $sl->get('SeriesToArticlesTable')->fetchByCond('article_id',$id);
                $seriesTable = $sl->get('Catalog\Model\SeriesTable');
                $relatedSeries = array();
                foreach($relatedSeriesIds as $rser){
                    $relatedSeries[] = $seriesTable->find($rser->series_id);
                }

                $nextArt = $this->getArticleTable()->find($nextId);
                $prevArt = $this->getArticleTable()->find($prevId);

                $htmlViewPart = new ViewModel();
                $htmlViewPart->setTerminal(true)
                    ->setTemplate('articles/articles/part/popup')
                    ->setVariables(array(
                        'article'         => $article,
                        'relatedSeries'   => $relatedSeries,
                        'nextArt'         => $nextArt,
                        'prevArt'         => $prevArt,

                        'sl'    => $sl
                    ));

                $content = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);
                $success = 1;
            }


            $response = $this->getResponse();
            if ($robot) {
                return $content;
            }
            $response->setContent(\Zend\Json\Json::encode(array(
                'success' => $success,
                'content' => $content,
            )));
            return $response;
        }
        return $this->redirect()->toRoute('catalog');
    }

    /**
     * @return ArticleTable array|object
     */
    public function getArticleTable()
    {
        if (!$this->articleTable) {
            $sm = $this->getServiceLocator();
            $this->articleTable = $sm->get('ArticlesTable');
        }
        return $this->articleTable;
    }
}