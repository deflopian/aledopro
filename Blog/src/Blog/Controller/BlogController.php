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

    public function indexAction()
    {
        $sl = $this->getServiceLocator();


        $news = $sl->get('NewsTable')->fetchAll('order ASC');
        $articles = $sl->get('ArticlesTable')->fetchAll('order ASC');
        $terms = $sl->get('TermsTable')->fetchAll('letter asc');
        $letters = ApplicationService::getLettersArr();
        $sortedTerms = array();
        foreach($terms as $term){
            $sortedTerms[$term->letter-1][] = $term;
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
            'news' => $news,
            'articles' => $articles,
            'parentUrl'     => '/blog/',
            'IdTerms' => \Zend\Json\Json::encode(ApplicationService::makeIdArrayFromObjectArray($terms)),
            'sortedTerms' => $sortedTerms,
            'letters' => $letters,
        );
    }
}