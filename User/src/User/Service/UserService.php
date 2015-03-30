<?php
namespace User\Service;

use Application\Service\MailService;
use User\Model\UserHistory;

class UserService
{
    public static $isManager = false;
    public static $godModeId = false;
    public static $godModeName = false;
    public static $godModePartnerGroupId = false;
    public static $isPartner = false;

    const USER_ACTION_OTHER = -1;
    const USER_ACTION_LOGIN = 1;
    const USER_ACTION_LOGOUT = 2;
    const USER_ACTION_ADD_TO_CART = 3;
    const USER_ACTION_REMOVE_FROM_CART = 4;
    const USER_ACTION_MAKE_ORDER = 5;
    const USER_ACTION_EDIT_ORDER = 6;
    const USER_ACTION_RECEIVED_A_PARTNERSHIP = 7;
    const USER_ACTION_RECEIVED_A_DISCOUNT = 8;
    const USER_ACTION_GAVE_A_DISCOUNT = 9;
    const USER_ACTION_GAVE_A_PARTNERSHIP = 10;
    const USER_ACTION_SENT_BUG_REPORT = 11;
    const USER_ACTION_SENT_SERVICE_REQUEST_MONTAGE = 12;
    const USER_ACTION_SENT_SERVICE_REQUEST_CALCULATION = 13;
    const USER_ACTION_SENT_SERVICE_REQUEST_MODERNISATION = 14;
    const USER_ACTION_SENT_SERVICE_REQUEST_CONSULT = 15;
    const USER_ACTION_SENT_SERVICE_REQUEST_PROJECT = 16;
    const USER_ACTION_SENT_REQUEST_PARTNERSHIP = 17;
    const USER_ACTION_REGISTER = 18;
    const USER_ACTION_FORGOT_PASSWORD = 19;
    const USER_ACTION_NEW_PASSWORD = 20;
    const USER_ACTION_EDIT_PROFILE_INFO = 21;
    const USER_ACTION_SENT_SERVICE_REQUEST = 22; //создаётс для любого запроса на услугу

    const USER_HISTORY_SECOND = 1;
    const USER_HISTORY_MINUTE = 2;
    const USER_HISTORY_HOUR = 3;
    const USER_HISTORY_DAY = 4;
    const USER_HISTORY_WEEK = 5;
    const USER_HISTORY_MONTH_28 = 6;
    const USER_HISTORY_MONTH_29 = 7;
    const USER_HISTORY_MONTH_30 = 8;
    const USER_HISTORY_MONTH_31 = 9;
    const USER_HISTORY_YEAR = 10;
    const USER_HISTORY_YEAR_LEAP = 11;

    public static $periods = array(
        self::USER_HISTORY_SECOND => 1,
        self::USER_HISTORY_MINUTE => 60,
        self::USER_HISTORY_HOUR => 3600,
        self::USER_HISTORY_DAY => 86400,
        self::USER_HISTORY_WEEK => 604800,
        self::USER_HISTORY_MONTH_28 => 2419200,
        self::USER_HISTORY_MONTH_29 => 2505600,
        self::USER_HISTORY_MONTH_30 => 2592000,
        self::USER_HISTORY_MONTH_31 => 2678400,
        self::USER_HISTORY_YEAR => 31536000,
        self::USER_HISTORY_YEAR_LEAP => 31622400,
    );

    public static $actionTypes = array(
        self::USER_ACTION_OTHER => "Иное действие",
        self::USER_ACTION_LOGIN => "Залогинился",
        self::USER_ACTION_LOGOUT => "Разлогинился",
        self::USER_ACTION_GAVE_A_DISCOUNT => "Назначил скидку партнёру",
        self::USER_ACTION_RECEIVED_A_PARTNERSHIP => "Стал партнёром",
        self::USER_ACTION_MAKE_ORDER => "Оформил заказ",
        self::USER_ACTION_ADD_TO_CART => "Добавил товар в корзину",
        self::USER_ACTION_EDIT_ORDER => "Отредактировал заказ",
        self::USER_ACTION_EDIT_PROFILE_INFO => "Отредактировал личную информацию",
        self::USER_ACTION_FORGOT_PASSWORD => "Запросил восстановление пароля",
        self::USER_ACTION_NEW_PASSWORD => "Получил новый пароль",
        self::USER_ACTION_RECEIVED_A_DISCOUNT => "Получил скидку",
        self::USER_ACTION_REGISTER => "Зарегистрировался на сайте",
        self::USER_ACTION_REMOVE_FROM_CART => "Убрал товар из корзины",
        self::USER_ACTION_SENT_BUG_REPORT => "Прислал отчёт о баге",
        self::USER_ACTION_SENT_REQUEST_PARTNERSHIP => "Захотел стать партнёром (отправил запрос через форму)",
        self::USER_ACTION_SENT_SERVICE_REQUEST => "Запросил услугу (любую)",
        self::USER_ACTION_SENT_SERVICE_REQUEST_CALCULATION => "Запросил рассчёт (услуга)",
        self::USER_ACTION_SENT_SERVICE_REQUEST_PROJECT => "Запросил проект (услуга)",
        self::USER_ACTION_SENT_SERVICE_REQUEST_MONTAGE => "Запросил монтаж (услуга)",
        self::USER_ACTION_SENT_SERVICE_REQUEST_MODERNISATION => "Запросил модернизацию (услуга)",
        self::USER_ACTION_SENT_SERVICE_REQUEST_CONSULT => "Запросил консультацию (услуга)",
    );

    public static $urlDescription = array(
        self::USER_ACTION_OTHER => "",
        self::USER_ACTION_LOGIN => "страница",
        self::USER_ACTION_LOGOUT => "страница",
        self::USER_ACTION_GAVE_A_DISCOUNT => "партнёр",
        self::USER_ACTION_RECEIVED_A_PARTNERSHIP => "",
        self::USER_ACTION_MAKE_ORDER => "посмотреть заказ",
        self::USER_ACTION_ADD_TO_CART => "добавленный товар",
        self::USER_ACTION_EDIT_ORDER => "посмотреть заказ",
        self::USER_ACTION_EDIT_PROFILE_INFO => "посмотреть профиль",
        self::USER_ACTION_FORGOT_PASSWORD => "",
        self::USER_ACTION_NEW_PASSWORD => "",
        self::USER_ACTION_RECEIVED_A_DISCOUNT => "посмотреть скидку",
        self::USER_ACTION_REGISTER => "",
        self::USER_ACTION_REMOVE_FROM_CART => "удалённый товар",
        self::USER_ACTION_SENT_BUG_REPORT => "прочесть отчёт",
        self::USER_ACTION_SENT_REQUEST_PARTNERSHIP => "посмотреть запрос",
        self::USER_ACTION_SENT_SERVICE_REQUEST => "",
        self::USER_ACTION_SENT_SERVICE_REQUEST_CALCULATION => "посмотреть запрос",
        self::USER_ACTION_SENT_SERVICE_REQUEST_PROJECT => "посмотреть запрос",
        self::USER_ACTION_SENT_SERVICE_REQUEST_MONTAGE => "посмотреть запрос",
        self::USER_ACTION_SENT_SERVICE_REQUEST_MODERNISATION => "посмотреть запрос",
        self::USER_ACTION_SENT_SERVICE_REQUEST_CONSULT => "посмотреть запрос",
    );


    public static function translateError($error) {

    }

    public static function formatHistoryUrl($url) {

        if (strstr($url, "http://")) {
            $url = substr($url, 7);
        }

        if (strstr($url, MailService::CURRENT_DOMEN)) {
            $url = substr($url, strlen(MailService::CURRENT_DOMEN));
        }

        $url = trim($url, '/');
        if (strlen($url)) {
            $url = '/' . $url . '/';
        }

        return $url;
    }

    public static function monthName($num) {
        $name = "Январь";

        switch ($num) {
            case 0:
            case 12:
                $name = "Декабрь";
                break;
            case 1:
                $name = "Январь";
                break;
            case 2:
                $name = "Февраль";
                break;
            case 3:
                $name = "Март";
                break;
            case 4:
                $name = "Апрель";
                break;
            case 5:
                $name = "Май";
                break;
            case 6:
                $name = "Июнь";
                break;
            case 7:
                $name = "Июль";
                break;
            case 8:
                $name = "Август";
                break;
            case 9:
                $name = "Сентябрь";
                break;
            case 10:
                $name = "Октябрь";
                break;
            case 11:
                $name = "Ноябрь";
                break;
        }

        return $name;
    }

    public static function _d($num, $str, $sex=0) {
        if ($str == 'действий') {
            if ($num%10 == 1) {
                $str = 'действие';
            } elseif ($num%10 > 1 && $num%10 < 5) {
                $str = 'действия';
            }
        }
        return $str;
    }

    public static function addHistoryAction($sl, $userId, $type = self::USER_ACTION_OTHER, $url = "", $time = false, $toUserId = null) {
        $history = new UserHistory();
        $history->user_id = $userId;
        $history->actionType = $type;
        $history->url = $url;
        $history->timer = $time;
        $history->to_user_id = $toUserId; // если активность между двумя юзерами, например, присвоение скидки одним юзером другому

        $sl->get('UserHistoryTable')->save($history);
    }
}