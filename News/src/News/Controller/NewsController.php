<?php
namespace News\Controller;

use Application\Service\ApplicationService;
use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use News\Model\News;

class NewsController extends AbstractActionController
{
    protected $pageInfoType = SeoService::NEWS;
    public function viewAction() {
        $sl = $this->getServiceLocator();

        $id = intval($this->params()->fromRoute('id', 0));

        /** @var News $news */
        $oneNews = $sl->get('NewsTable')->find($id);

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::NEWS, $id );


        $arrayIds = array();
        $news = $this->getServiceLocator()->get('NewsTable')->fetchAll('order asc');
        foreach ($news as $one) {
            $arrayIds[] = $one->id;
        }

        $nextId = CatalogService::getNextId($id, $arrayIds);
        $prevId = CatalogService::getPrevId($id, $arrayIds);

        $nextProd = $sl->get('NewsTable')->find($nextId);
        $prevProd = $sl->get('NewsTable')->find($prevId);

        $this->layout()->pageTitle = $oneNews->title;
        $this->layout()->breadCrumbs  = array(
            array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            array('link'=> $this->url()->fromRoute('news'), 'text'=>ucfirst('Новости'))
        );
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setVariables(array(
            'news'   => $oneNews,
            'nextProd' => $nextProd,
            'prevProd' => $prevProd,
            'seoData' => $seoData,
            'sl'        => $sl
        ));
        return $htmlViewPart;
    }

    public function indexAction()
    {
        $news = $this->getServiceLocator()->get('NewsTable')->fetchAll('order ASC');
        $arrayIds = array();
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::NEWS, 1 );
        foreach ($news as $one) {
            $arrayIds[] = $one->id;
        }

        $newsSorted = ApplicationService::sortForThreeArrays($news);


        $this->layout()->setVariables(array(
            'seoData' => $seoData,

            'pageTitle' => 'Новости',
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
                array('link'=> $this->url()->fromRoute('blog'), 'text'=>ucfirst('Блог'))
            ),
        ));

        return array(
            'seoData' => $seoData,
            'parentUrl'     => '/news/',
            'news1' => $newsSorted[0],
            'news2' => $newsSorted[1],
            'news3' => $newsSorted[2],
        );
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
                $sl = $this->getServiceLocator();
                $newstable = $sl->get('NewsTable');
                $news = $newstable->find($id);
                $nextNews = $newstable->find($nextId);
                $prevNews = $newstable->find($prevId);

                $htmlViewPart = new ViewModel();
                $htmlViewPart->setTerminal(true)
                    ->setTemplate('news/news/part/popup')
                    ->setVariables(array(
                        'news'       => $news,
                        'nextNews'   => $nextNews,
                        'prevNews'   => $prevNews,
                        'sl'         => $sl,
                        'robot'         => $robot,
                    ));

                $content = $sl->get('viewrenderer')->render($htmlViewPart);
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
}