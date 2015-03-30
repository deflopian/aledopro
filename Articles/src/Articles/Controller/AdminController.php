<?php
namespace Articles\Controller;

use Application\Controller\SampleAdminController;
use Catalog\Service\CatalogService;
use Articles\Model\Article;
use Articles\Model\StoA;
use Info\Service\SeoService;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Articles\Model\Article';
    private $sToATable = 'SeriesToArticlesTable';

    public function viewAction()
    {
        $return = parent::viewAction();

        if(is_array($return)){
            $id = (int) $this->params()->fromRoute('id', 0);
            if (!$id) {
                return $this->redirect()->toRoute('zfcadmin/'.$this->url);
            }

            $relatedSeriesIds = $this->getServiceLocator()->get($this->sToATable)->fetchByCond('article_id', $id);
            $seriesTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
            $relatedSeries = array();
            foreach($relatedSeriesIds as $rser){
                $relatedSeries[] = $seriesTable->find($rser->series_id);
            }
            $return['relatedSeries'] = $relatedSeries;

            $allSeries = $seriesTable->fetchAll('order asc');
            $data = CatalogService::getSeriesAndTags($allSeries);
            $return['tags'] = \Zend\Json\Json::encode($data['tags']);
            $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::ARTICLES, $id );
            $return['seoData'] = $seoData;
        }

        return $return;
    }

    public function saveTagitAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $articleId = $request->getPost('id', false);
            $seriesIds = $request->getPost('tagitIds', false);
            $success = 0;

            if ($articleId && $seriesIds) {
                $table = $this->getServiceLocator()->get($this->sToATable);

                foreach(explode(',', $seriesIds) as $sid){
                    $stoa = new StoA();
                    $stoa->exchangeArray(array(
                        'article_id' => $articleId,
                        'series_id'  => $sid,
                    ));

                    $table->save($stoa);
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function removeParentTagitAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $sid = $request->getPost('id', false);
            $aid = $request->getPost('parentId', false);
            $success = 0;

            if ($aid && $sid) {
                $table = $this->getServiceLocator()->get($this->sToATable);
                $table->del(array('aid' => $aid, 'sid' => $sid));
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }
}