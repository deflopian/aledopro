<?php
namespace IPGeoBase\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use IPGeoBase\Service\GeoService;

class GeoBannerController extends AbstractActionController
{
    public function viewAction() {
		return $this->redirect()->toRoute('catalog');
    }

    public function indexAction()
    {
		if ($this->getRequest()->isXmlHttpRequest()) {
			$ip = $_SERVER['REMOTE_ADDR'];
			$sl = $this->getServiceLocator();
			
			$post = $request->getPost()->toArray();
			$section_type = $post['section_type'];
			$section_id = $post['section_id'];
			
			$arr = array();
			
			if ($section_type && $section_id) {			
				$banner = GeoService::getGeoBanner($sl, $ip, $section_type, $section_id);
				
				if ($banner) {
					$arr = array(
						'id' => $banner->id,
						'text' => $banner->text,
						'link' => $banner->link,
						'img' => $banner->img,
					);
				}
			}
			
			$response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($arr));
            return $response;
		}
		return $this->redirect()->toRoute('catalog');
    }
}