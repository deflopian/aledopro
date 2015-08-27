<?php
namespace Articles\Controller;

use Application\Controller\SampleAdminController;
use Articles\Model\ArticleBlock;
use Articles\Model\ArticleTag;
use Articles\Model\TagToArticle;
use Catalog\Service\CatalogService;
use Articles\Model\Article;
use Articles\Model\StoA;
use Info\Service\SeoService;
use Zend\Json\Json;

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
        $this->imgFields=array('img', 'img2', 'preview');
        //$return = parent::viewAction();
        $return = array();
        $id = $this->params()->fromRoute('id', 0);
        if ($id && is_numeric($id)) {
            $return['entity'] = $this->getServiceLocator()->get('ArticlesTable')->find($id);
        } else {
            $article = $this->getServiceLocator()->get('ArticlesTable')->fetchByCond('alias', $id);
            $return['entity'] = reset($article);
            $id = $return['entity']->id;
        }

        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($return['entity']->$imgField) {
                    $file = $fileTable->find($return['entity']->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $return['entity']->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

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
            if (count($allTags)>0 && count($tagIds)>0) {
                $tags = $tagsTable->fetchByCond('id', $tagIds);
            } else {
                $tags = array();
            }
            $currentTags = array();
            foreach ($tags as $tag) {
                $name = array();
                $name['label'] = $tag->name;
                $name['value'] = $tag->id;
                $currentTags[] = $name;
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
            $return['currentTags'] = \Zend\Json\Json::encode($currentTags);
            $allSeries = $seriesTable->fetchAll('order asc');
            $data = CatalogService::getSeriesAndTags($allSeries);
            $return['tags'] = \Zend\Json\Json::encode($names);
            $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::ARTICLES, $id );
            $return['seoData'] = $seoData;
        }

        return $return;
    }

    public function addTagAction() {
        $request = $this->getRequest();

        $success = 0;
        $sl = $this->getServiceLocator();
        $data = Json::decode($request->getContent(), Json::TYPE_ARRAY);


        if ($request->isPost()) {
            $tagToArticleTable = $sl->get('TagToArticlesTable');
            $curr = $tagToArticleTable->fetchByConds(array('article_id' => $data['article_id'], 'tag_id' => $data['tag_id']));
            if (!$curr || count($curr) == 0) {
                $tagToArticle = new TagToArticle();
                $tagToArticle->article_id = $data['article_id'];
                $tagToArticle->tag_id = $data['tag_id'];
                $tagToArticleTable->save($tagToArticle);

                $success = 1;

            }
            $this->response->setContent(Json::encode(array('success' => $success)))->setStatusCode(200);
            return $this->response;
        } else {
            return $this->redirect()->toRoute('admin/blog');
        }
    }

    public function addNewTagAction() {
        $request = $this->getRequest();

        $success = 0;
        $sl = $this->getServiceLocator();
        $data = Json::decode($request->getContent(), Json::TYPE_ARRAY);

        if ($request->isPost()) {
            $tagTable = $sl->get('ArticleTagsTable');
            $tagToArticleTable = $sl->get('TagToArticlesTable');
            $tag = new ArticleTag();
            $tag->name = $data['tag'];
            $tagId = $tagTable->save($tag);

            $tagToArticle = new TagToArticle();
            $tagToArticle->article_id = $data['article_id'];
            $tagToArticle->tag_id = $tagId;
            $tagToArticleTable->save($tagToArticle);

            $success = 1;

            $this->response->setContent(Json::encode(array('success' => $success)))->setStatusCode(200);
            return $this->response;
        } else {
            return $this->redirect()->toRoute('admin/blog');
        }
    }

    public function removeTagAction() {
        $request = $this->getRequest();

        $success = 0;
        $sl = $this->getServiceLocator();
        $data = Json::decode($request->getContent(), Json::TYPE_ARRAY);

        if ($request->isPost()) {
            $tagToArticleTable = $sl->get('TagToArticlesTable');
            $curr = $tagToArticleTable->fetchByConds(array('article_id' => $data['article_id'], 'tag_id' => $data['tag_id']));
            if (count($curr) > 0) {
                $curr = reset($curr);
                $tagToArticleTable->del($curr->id);
                $success = 1;
            }

            $this->response->setContent(Json::encode(array('success' => $success)))->setStatusCode(200);
            return $this->response;
        } else {
            return $this->redirect()->toRoute('admin/blog');
        }
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
	
	public function changeActivityStatusAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $val = $request->getPost('val', false);
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id && $val !== false) {
                $table = $this->getServiceLocator()->get($this->table);
                $vacancy = $table->find($id);
                $vacancy->active = $val;
                $table->save($vacancy);

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('admin/blog');
    }
}