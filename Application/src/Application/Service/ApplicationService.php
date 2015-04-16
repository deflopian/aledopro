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

            'Ы', 'Ж',  'Ч',  'Щ',   'Ш',  'Ю',  'А', 'Б', 'В', 'Г', 'Д', 'Е', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ъ', 'Ь', 'Я',
            ' ',
        );
        $lat = array(
            'i', 'zh', 'ch', 'sht', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'y', 'x', 'a',
            'i', 'zh', 'ch', 'sht', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'y', 'x', 'a',
            '-',
        );

        if($textcyr) {
            return str_replace($cyr, $lat, $textcyr);
        } else if($textlat) {
            return str_replace($lat, $cyr, $textlat);
        } else {
            return null;
        }
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