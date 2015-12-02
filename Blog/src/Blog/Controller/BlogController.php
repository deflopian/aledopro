<?php
namespace Blog\Controller;

use Application\Service\ApplicationService;
use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;

class BlogController extends AbstractActionController
{
    protected $pageInfoType = SeoService::BLOG;

    public function viewAction() {

    }

    public function brandsAction() {

    }

    public function viewBrandAction() {

    }

    public function tagAction()
    {
        $id = $this->params()->fromRoute('id', false);
        if (empty($id)) return $this->redirect()->toRoute('blog');

        $sl = $this->getServiceLocator();
        $tagsTable = $sl->get('ArticleTagsTable');
        $tagLinksTable = $sl->get('TagToArticlesTable');
        if (is_numeric($id)) {
            $tag = $tagsTable->find($id);
        } else {
            $tag = $tagsTable->fetchByCond('name', $id);
            $tag = reset($tag);
        }

        if (!$tag) {
            return $this->redirect()->toRoute('blog');
        }
        $tagLinks = $tagLinksTable->fetchByCond('tag_id', $tag->id);
        $articleIds = array();
        foreach ($tagLinks as $taglink) {
            if (!in_array($taglink->article_id, $articleIds)) {
                $articleIds[] = $taglink->article_id;
            }
        }
        $articleTable = $sl->get('ArticlesTable');
        if ($articleIds) {
            $articles = $articleTable->fetchByCond('id', $articleIds);
        } else {
            $articles = array();
        }


        $fileTable = $this->getServiceLocator()->get('FilesTable');

        foreach ($articles as &$article) {

            foreach (array('preview') as $imgField) {
                if ($article->$imgField) {
                    $file = $fileTable->find($article->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $article->$imgFieldAndName = $file->name;
                    }
                }
            }


            $tagIds = array();

            $tagLinks = $tagLinksTable->fetchByCond('article_id', $article->id);
            foreach ($tagLinks as $link) {
                $tagIds[] = $link->tag_id;
            }
            if ($tagIds) {
                $tags = $tagsTable->fetchByCond('id', $tagIds);
                $article->tags = $tags;
            } else {
                $article->tags = array();
            }
        }
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::BLOG, 1 );
        $this->layout()->pageTitle = 'Блог';

        return array(
            'tag' => $tag,
            'seoData' => $seoData,
            'articles' => $articles
        );
    }

    public function indexAction()
    {
        $sl = $this->getServiceLocator();
		
		$articles = $sl->get('ArticlesTable')->fetchByCond('deleted', 0, 'order ASC');
		
		if ($this->zfcUserAuthentication()->getIdentity()) {
			$uid = $this->zfcUserAuthentication()->getIdentity()->getId();
			$user = $sl->get('UserTable')->find($uid);
				
			$roleLinker = $sl->get('RoleLinkerTable')->find($user->user_id, 'user_id');
			$role = $roleLinker->role_id;

			if ($role == 'admin' || $role == 'manager') {
				$articles = $sl->get('ArticlesTable')->fetchAll('order ASC');
			}
		}
		
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        $tagToArticleTable = $this->getServiceLocator()->get('TagToArticlesTable');
        $tagsTable = $this->getServiceLocator()->get('ArticleTagsTable');

        foreach ($articles as &$article) {
			
            foreach (array('preview') as $imgField) {
                if ($article->$imgField) {
                    $file = $fileTable->find($article->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $article->$imgFieldAndName = $file->name;
                    }
                }
            }


            $tagIds = array();

            $tagLinks = $tagToArticleTable->fetchByCond('article_id', $article->id);
            foreach ($tagLinks as $link) {
                $tagIds[] = $link->tag_id;
            }
            if ($tagIds) {
                $tags = $tagsTable->fetchByCond('id', $tagIds);
                $article->tags = $tags;
            } else {
                $article->tags = array();
            }
        }
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::BLOG, 1 );
        $this->layout()->pageTitle = 'Блог';
        $this->layout()->setVariables(
            array(
                'seoData' => $seoData
            )
        );
        return array(
            'seoData' => $seoData,
            'articles' => $articles,
            'parentUrl'     => '/blog/',
        );
    }
}