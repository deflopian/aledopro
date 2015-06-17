<?php
namespace Articles\Controller;

use Application\Controller\SampleAdminController;
use Articles\Model\ArticleBlock;
use Catalog\Service\CatalogService;
use Articles\Model\Article;
use Articles\Model\StoA;
use Info\Service\SeoService;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Articles\Model\Article';
    private $sToATable = 'SeriesToArticlesTable';

    public function addEntityAction()
    {
        $this->setData();
        $type = false;
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $type = $request->getPost('page_info_type', false);
            $success = 0;

            if ($title) {
                $data = array('title' => $title,);

                $entity = new $this->entityName;
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get($this->table)->save($entity);

                if($newId){
                    for ($i=1; $i<11; $i++) {
                        $block = new ArticleBlock();
                        $block->article_id = $newId;
                        $block->order = $i;
                        $this->getServiceLocator()->get('ArticleBlocksTable')->save($block);
                    }
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


    public function updateEditableAction()
    {
        $this->setData();
        $type = false;
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['page_info_type']) {
                $type = $post['page_info_type'];
            }

            $name = $post['name'];
            $names = explode('_', $name);
            if ($post['pk']) {
                if (isset($names[1]) && is_numeric($names[1])) {
                    $blockId = $names[1];
//                    $data[$names[0]] = $post['value'];
                    $field = $names[0];

                    $articleBlockTable = $this->getServiceLocator()->get('ArticleBlocksTable');
                    $articleBlock = $articleBlockTable->find($blockId);

                    if ($articleBlock) {
                        $articleBlock->$field = $post['value'];
                        $articleBlockTable->save($articleBlock);
                        $success = 1;
                    }
                } else {

                    $data['id'] = $post['pk'];
                    $data[$post['name']] = $post['value'];

                    $entity = new $this->entityName;
                    $entity->exchangeArray($data);

                    $this->getServiceLocator()->get($this->table)->save($entity);

                    if ($type !== false) {
                        $this->updateLastModified($type);
                    }
                    $success = 1;
                }

            }
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function viewAction()
    {
        $this->imgFields=array('img', 'preview');
        $return = parent::viewAction();
        $id = (int) $this->params()->fromRoute('id', 0);
        if(is_array($return)){
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

            $tagsTable = $this->getServiceLocator()->get('ArticleTagsTable');
            $allTags = $tagsTable->fetchAll();
            $tagToArticleTable = $this->getServiceLocator()->get('TagToArticlesTable');
            $tagIds = array();

            $tagLinks = $tagToArticleTable->fetchByCond('article_id', $id);
            foreach ($tagLinks as $link) {
                $tagIds[] = $link->tag_id;
            }
            if (count($allTags)>0) {
                $tags = $tagsTable->fetchByCond('id', $tagIds);
            } else {
                $tags = array();
            }

            $names = array();
            foreach ($allTags as $tag) {
                $name = array();
                $name['label'] = $tag->name;
                $name['value'] = $tag->id;
                $names[] = $name;
            }

            $blocksTable = $this->getServiceLocator()->get('ArticleBlocksTable');
            $blocks = $blocksTable->fetchByCond('article_id', $id, 'order ASC');
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($blocks as &$block) {

                foreach ($this->imgFields as $imgField) {
                    if ($block->$imgField) {
                        $file = $fileTable->find($block->$imgField);
                        if ($file) {
                            $imgFieldAndName = $imgField . "_name";
                            $block->$imgFieldAndName = $file->name;
                        }
                    }
                }
            }
            $return['entity']->blocks = $blocks;
            $return['currentTags'] = $tags;
            $allSeries = $seriesTable->fetchAll('order asc');
            $data = CatalogService::getSeriesAndTags($allSeries);
            $return['tags'] = \Zend\Json\Json::encode($names);
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