<?php
namespace IPGeoBase\Service;

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
	
	public function getGeoBanner($sl, $ip) {
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
		
		$geoBannerMapper = GeoBannerMapper::getInstance($sl);
		$banners = $geoBannerMapper->fetchGeoBanners($country, $region);
		
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