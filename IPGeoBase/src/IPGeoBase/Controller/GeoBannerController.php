<?php
namespace IPGeoBase\Controller;

use Application\Service\MailService;
use IPGeoBase\Service\GeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Header\SetCookie;

class GeoBannerController extends AbstractActionController
{
    public function viewAction() {
		return $this->redirect()->toRoute('catalog');
    }
	
	public function indexAction() {
		return $this->redirect()->toRoute('catalog');
    }

    public function getAction()
    {
		$ip = $_SERVER['REMOTE_ADDR'];
		$sl = $this->getServiceLocator();
		
		$post = $this->getRequest()->getPost()->toArray();
		$section_type = $post['section_type'];
		$section_id = $post['section_id'];
		
		$res = array();
		$response = $this->getResponse();
		
		if ($section_type && $section_id)
		{
			$hidden_banners = array();
			if (isset($_COOKIE['geoBanners']) && is_array($_COOKIE['geoBanners']))
			{
				foreach ($_COOKIE['geoBanners'] as $key => $val)
				{
					if (!$val) continue;
					$hidden_banners[] = $key;
				}
			}
			if (isset($_COOKIE['geoBannersTimes']) && is_array($_COOKIE['geoBannersTimes']))
			{
				foreach ($_COOKIE['geoBannersTimes'] as $key => $val)
				{
					if (in_array($key, $hidden_banners)) continue;
					if ($val < 4) continue;
					$hidden_banners[] = $key;
				}
			}
			
			if ($banner = GeoService::getGeoBanner($sl, $ip, $section_type, $section_id, $hidden_banners))
			{
				$res = array(
					'id' => $banner->id,
					'text' => $banner->text,
					'link' => $banner->link
				);
					
				$cookie = new SetCookie();
				$cookie->setName('geoBanners[' . $banner->id . ']');
				$cookie->setValue(1);
				$cookie->setExpires(time() + 86400);
				
				$response->getHeaders()->addHeader($cookie);
				
				$times = 0;
				if (isset($_COOKIE['geoBannersTimes'][$banner->id]))
				{
					$times = (int)$_COOKIE['geoBannersTimes'][$banner->id];
				}
				
				$cookie = new SetCookie();
				$cookie->setName('geoBannersTimes[' . $banner->id . ']');
				$cookie->setValue($times + 1);
				$cookie->setExpires(time() + (86400 * 365));
				
				$response->getHeaders()->addHeader($cookie);
			}
		}
			
        $response->setContent(\Zend\Json\Json::encode($res));
        return $response;
    }
}