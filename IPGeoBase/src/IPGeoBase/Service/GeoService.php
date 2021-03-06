<?php
namespace IPGeoBase\Service;

use Application\Service\ApplicationService;
use IPGeoBase\Mapper\GeoBannerMapper;

class GeoService {
    public static $defaultCountry = 'RU';
	public static $defaultRegion = '48';

    public static function getCountriesList($sl) {
        $res = array();
		
		$geoBannerMapper = GeoBannerMapper::getInstance($sl);
		$countries = $geoBannerMapper->fetchGeoCountries();
		
		foreach ($countries as $item) {
			$res[$item->code] = $item->title;
		}
		
        return $res;
    }
	
	public static function getRegionsList($sl, $code) {
        $res = array();
		
		$geoBannerMapper = GeoBannerMapper::getInstance($sl);
		$country = $geoBannerMapper->fetchGeoCountry($code);
		
		if ($country) {
			$regions = $geoBannerMapper->fetchGeoRegions($country->id);
			
			foreach ($regions as $item) {
				$res[$item->code] = $item->title;
			}
		}
		
        return $res;
    }
	
	public static function getGeoBanner($sl, $ip, $section_type, $section_id, $no_arr = array()) {
		$data = geoip_record_by_name($ip);
		
        $country = self::$defaultCountry;
        $region = self::$defaultRegion;
		
        if (is_array($data) && !empty($data['country_code'])) {
			$country = $data['country_code'];
			
            if (!empty($data['region'])) {
                $region = $data['region'];
            }
			else {
				if ($country != self::$defaultCountry) {
					$region = '';
				}
			}
        }
		
		if ($section_type == -1) {
			if ((!ApplicationService::isDomainZone('by') && $country == 'BY') || (ApplicationService::isDomainZone('by') && $country == 'RU')) {
				$region = '';
			}
			else return false;
		}
		
		$geoBannerMapper = GeoBannerMapper::getInstance($sl);
		$banners = $geoBannerMapper->fetchGeoBanners($country, $region, $section_type, $section_id, $no_arr);
		
		if (!empty($banners)) {
			$ind = mt_rand(0, count($banners) - 1);
			
			if (isset($banners[$ind])) {
				return $banners[$ind];
			}
			return false;
		}
		return false;
    }
}