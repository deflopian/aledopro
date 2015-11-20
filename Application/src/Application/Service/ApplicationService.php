<?php
namespace Application\Service;

use Zend\Config\Reader\Ini;
use Zend\View\Model\ViewModel;



class ApplicationService
{
    const MEDIA_TYPE_IMG = 1;
    const MEDIA_TYPE_VIDEO = 2;

    const BANNER_POSITION_LEFT = 0;
    const BANNER_POSITION_CENTER = 1;
    const BANNER_POSITION_RIGHT = 2;

    const ALEDO_POPUP_ERROR = 0;
    const ALEDO_POPUP_SUCCESS = 1;
    const ALEDO_POPUP_LOGIN = 2;
    const ALEDO_POPUP_REGISTER = 3;
    const ALEDO_POPUP_CART_BUY = 4;
    const ALEDO_POPUP_CART_BUY_WITHOUT_REGISTER = 5;
    const ALEDO_POPUP_CART_REGISTER = 6;
    const ALEDO_POPUP_REGISTER_SUCCESS = 7;
    const ALEDO_POPUP_CART_REGISTER_SUCCESS = 8;
    const ALEDO_POPUP_PARTNER_CARD = 9;
    const ALEDO_POPUP_SERVICE_CALCULATE = 10;
    const ALEDO_POPUP_FORGOT = 11;
    const ALEDO_POPUP_ORDER_SUCCESS = 12;
    const ALEDO_POPUP_QUESTION_SUCCESS = 13;
    const ALEDO_POPUP_CALLBACK = 14;
	const ALEDO_POPUP_VACANCY_REQUEST = 15;
	const ALEDO_POPUP_VACANCY_REQUEST_SUCCESS = 16;
	const ALEDO_POPUP_PARTNER_CARD_SUCCESS = 17;

    private static $aledoPopups = array(
        self::ALEDO_POPUP_ERROR => 'error',
        self::ALEDO_POPUP_SUCCESS => 'stuffsend',
        self::ALEDO_POPUP_LOGIN => 'login',
        self::ALEDO_POPUP_REGISTER => 'register',
        self::ALEDO_POPUP_CART_BUY => 'cart-buy',
        self::ALEDO_POPUP_CART_BUY_WITHOUT_REGISTER => 'cart-buy-without-register',
        self::ALEDO_POPUP_CART_REGISTER => 'registerFromCart',
        self::ALEDO_POPUP_REGISTER_SUCCESS => 'regsuccess',
        self::ALEDO_POPUP_CART_REGISTER_SUCCESS => 'regsuccessFromCart',
        self::ALEDO_POPUP_PARTNER_CARD => 'partner-card',
        self::ALEDO_POPUP_SERVICE_CALCULATE => 'service_calculate',
        self::ALEDO_POPUP_FORGOT => 'forgot',
        self::ALEDO_POPUP_ORDER_SUCCESS => 'ordersend',
        self::ALEDO_POPUP_QUESTION_SUCCESS => 'question_send',
        self::ALEDO_POPUP_CALLBACK => 'callback',
		self::ALEDO_POPUP_VACANCY_REQUEST => 'vacancy-request',
		self::ALEDO_POPUP_VACANCY_REQUEST_SUCCESS => 'vacancy-request-success',
		self::ALEDO_POPUP_PARTNER_CARD_SUCCESS => 'partner-success',
    );
    
    private static $bannerPositionNames = array(
        self::BANNER_POSITION_LEFT => 'Слева',
        self::BANNER_POSITION_CENTER => 'По центру',
        self::BANNER_POSITION_RIGHT => 'Справа',
    );

    public static function getFormedDate($date, $exactly = true)
    {
        $monthes = array(
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        );

        $hi = date(' H:i', strtotime($date));
        $fullDate = date('d/m/Y', strtotime($date));

        if($exactly && $fullDate == date('d/m/Y')) {
            $date = 'сегодня в '. $hi;
        } else if($exactly && $fullDate == date('d/m/Y',time() - (24 * 60 * 60))) {
            $date = 'вчера в '. $hi;
        } else {
            $date = (int)date('d', strtotime($date)) . ' ' . $monthes[(int)date('m', strtotime($date))] . ' ' . (int)date('Y', strtotime($date)) . ' года';
        }

        return $date;
    }

    public static function getBannerPositionName($positionId) {
        return isset(self::$bannerPositionNames[$positionId]) ? self::$bannerPositionNames[$positionId] : 'Слева';
    }

    public static function transliterate($textcyr = null, $textlat = null)
    {
        $cyr = array(
			'ы', 'ж',  'ч',  'щ',   'ш',  'ю',  'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ъ', 'ь', 'я', 'э',
            'Ы', 'Ж',  'Ч',  'Щ',   'Ш',  'Ю',  'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ъ', 'Ь', 'Я', 'Э',
            ' ',
        );
        $lat = array(
            'y', 'zh', 'ch', 'shch', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'yo', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'kh', 'ts', '', '', 'ya', 'e',
            'y', 'zh', 'ch', 'shch', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'yo', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'kh', 'ts', '', '', 'ya', 'e',
            '_',
        );

        if($textcyr) {
            return str_replace($cyr, $lat, $textcyr);
        } else if($textlat) {
            return str_replace($lat, $cyr, $textlat);
        } else {
            return null;
        }
    }
	
    public static function isDomainZone($zone)
    {
        $parts = explode('.', $_SERVER['SERVER_NAME']);
        if (count($parts) > 1) {
			$parts = array_reverse($parts);
			if ($zone == $parts[0]) return true;
			return false;
		}
		else {
			if ($zone == 'ru') return true;
			return false;
		}
    }
	
    public static function formatRawTel($str)
    {
        return preg_replace('/\D/', '', $str);
    }
	
	public static function updateCurrencyRate($currency)
	{
		$url = 'http://www.cbr.ru/scripts/XML_daily.asp';
		$path = $_SERVER['DOCUMENT_ROOT'] . '/rate_' . strtolower($currency) . '.txt';
		$value = 0;

		$file_content = file_get_contents($url);
		if ($file_content !== false) {
			$items = simplexml_load_string($file_content);
			
			foreach($items as $item) {
				if ($item->CharCode && $item->CharCode == $currency) {
					$value = ((float)str_replace(',', '.', $item->Value)) / ((int)$item->Nominal);
				}
			}
        }
		
		if ($value) {
			if (file_put_contents($path, $value) !== false)	return true;
			return false;
		}
		
		return false;
    }
	
	public static function getCurrencyRate($currency)
	{
		$path = $_SERVER['DOCUMENT_ROOT'] . '/rate_' . strtolower($currency) . '.txt';
		$value = file_get_contents($path);
		return ($value !== false) ? $value : 1;
	}

    public static function makeIdArrayFromObjectArray($objArr, $idFiled = 'id')
    {
        $res = array();
        foreach($objArr as $enity){
            $res[$enity->$idFiled] = $enity;
        }
        return $res;
    }

    public static function getLettersArr()
    {
        return array(
            'а', 'б', 'в', 'г', 'д', 'e', 'ж', 'з', 'и', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'э', 'ю', 'я',
        );
    }

    public static function sortForThreeArrays($entities)
    {
        if(!$entities){ return array(); }
        
        $group1 = $group2 = $group3 = array();

        $i = 0;
        foreach($entities as $entity){
            if($i == 3){ $i = 0; }

            if($i == 0){
                $group1[] = $entity;
            } else if($i==1){
                $group2[] = $entity;
            } else{
                $group3[] = $entity;
            }

            $i++;
        }

        return array($group1, $group2, $group3);
    }

    public static function getPopupName($popupType) {
        if (array_key_exists($popupType, self::$aledoPopups)) {
            return self::$aledoPopups[$popupType];
        } else {
            return 'error';
        }
    }
    
    public static function getValidationFormMessages()
    {
        $reader = new Ini();
        $data   = $reader->fromFile( $_SERVER['DOCUMENT_ROOT'].'/form-config.ini');

        $res = array();
        if ( ! empty($data)){
            foreach ($data as $field => $validators){
                $res[$field] = array();
                foreach($validators as $name=>$options){
                    $res[$field][] = array(
                        'name' => $name,
                        'options' => $options
                    );
                }
            }
        }
        return $res;
    }

    public static function renderFormFileInput($sl, $title='Прикрепить файл', $formats = '(txt, doc, pdf)', $isCart = false, $isRequired = false, $lastFile = false)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('application/index/utils/form-file')
            ->setVariable('title', $title)
            ->setVariable('formats', $formats)
            ->setVariable('isCart', $isCart)
            ->setVariable('isRequired', $isRequired)
            ->setVariable('lastFile', $lastFile ? $lastFile : "");

        return $sl->get('viewrenderer')->render($htmlViewPart);
    }
}