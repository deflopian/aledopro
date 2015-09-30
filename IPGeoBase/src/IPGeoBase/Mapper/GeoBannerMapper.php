<?php
namespace IPGeoBase\Mapper;

class GeoBannerMapper {
    private static $instance = null;
    private $sl = null;

    private function __construct($sl) {
        $this->sl = $sl;
    }

    private function getGeoBannerTable() {
        return $this->sl->get('GeoBannersTable');
    }
	
	private function getGeoCountryTable() {
        return $this->sl->get('GeoCountriesTable');
    }
	
	private function getGeoRegionTable() {
        return $this->sl->get('GeoRegionsTable');
    }

    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new GeoBannerMapper($sl);
        }
        return self::$instance;
    }

    public function fetchGeoBanners($country = false, $region = '') {
        if ($country) {
			$res = array();
			
			if ($region) {
				$res = $this->getGeoBannerTable()->fetchByConds(array('country_code' => $country, 'region_code' => ''));
			}
			$res = array_merge($res, $this->getGeoBannerTable()->fetchByConds(array('country_code' => $country, 'region_code' => $region)));
			
			return $res;
		}
		return $this->getGeoBannerTable()->fetchAll('id ASC');
    }
	
	public function fetchGeoBanner($id) {
        return $this->getGeoBannerTable()->find($id);
    }
	
	public function fetchGeoCountries() {
        return $this->getGeoCountryTable()->fetchAll('id ASC');
    }
	
	public function fetchGeoCountry($code) {
        $countries = $this->getGeoCountryTable()->fetchByCond('code', $code);
		if (is_array($countries) && $countries[0]) return $countries[0];
		return false;
    }
	
	public function fetchGeoRegions($country_id) {
        return $this->getGeoRegionTable()->fetchByCond('country_id', $country_id, 'id ASC');
    }
}