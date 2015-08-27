<?php
namespace Commercials\Model;

use Application\Model\SampleModel;

class Commercial extends SampleModel //toArray & exchangeArray
{
    public $id;
    public $title; //название КП
    public $user_id; //Пользователь, который создал КП. Необязательное поле
    public $datetime;
    public $summ;
    public $uid; //айдишка КП, уникальная для конкретного пользователя (чтобы не передавать юзерам полные $id)
    //public $rooms; //элементы отчёта. Прилинковываются в CommercialMapper
    public $price_user_id; //Пользователь, чьи цены (скидки) применяются для данного КП
}