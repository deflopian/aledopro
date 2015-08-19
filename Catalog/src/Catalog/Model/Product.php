<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class Product extends SampleModel
{
    public $group_code  = 0;         //Код группы
    public $id          = 0;         //Код товара
    public $title   = 0;      //Наименование товара
    public $brand   = 0;      //Метка
    public $year_made   = 0; //Год изготовления
    public $free_balance   = 0; //Свободный остаток
    public $expected_count   = 0; //Ожидаемое кол-во
    public $price_without_nds   = 0; //Цена без НДС(руб)
    public $wholesale_price   = 0; //Оптовая цена(руб)
    public $text   = 0;       //Описание
    public $file   = 0; //файл
    public $type   = 0;       //Тип
    public $power   = 0;      //Мощность
    public $count_of_diodes   = 0; //Число светодиодов
    public $electro_power   = 0;//Питание?
    public $equipment_IP   = 0;//Источник питания
    public $viewing_angle   = 0;//Угол свечения
    public $luminous_flux   = 0;//Световой поток
    public $ip_rating   = 0;//Класс защиты
    public $material   = 0;//Материал
    public $case_color   = 0;//Цвет корпуса
    public $color_of_light   = 0;//Цвет свечения
    public $socle   = 0;//Цоколь
    public $equivalent   = 0;//Эквивалент
    public $manufacturer_of_diodes   = 0;//Светодиоды
    public $diffuser   = 0;//Диффузер
    public $life   = 0;//Срок службы
    public $length   = 0;//Длина
    public $u_out   = 0;//Вых. напряжение
    public $i_out   = 0;//Вых. I
    public $current_per_channel   = 0;//Ток на канал
    public $number_of_channels   = 0;//Число каналов
    public $mode   = 0;//Прот. Управл.
    public $temperature_conditions   = 0;//Терморежим
    public $construction   = 0;//Конструкция
    public $method_dim   = 0;//Метод дим.
    public $cri   = 0;// CRI
    public $warranty   = 0;//Гарантия
    public $dimming   = 0;//Диммирование
    public $control   = 0;//ДУ
    public $management_protocol   = 0;//Режим
    public $bulb   = 0;//Колба
    public $color_temperature   = 0;//Цв. температура
    public $scenario   = 0;
    public $sv_effect   = 0;// Св. эффект
    public $u_in   = 0;//Вх. напряжение
    public $ECE   = 0;//КПД
    public $cos   = 0;//косинус фи
    public $controller_type   = 0;// Тип контроллера
    public $weight   = 0;// Вес
    public $seriesName = ""; //Название серии в текстовом виде для дальнейшего поиска
    public $opt1 = "";
    public $opt2 = "";
    public $opt3 = "";
    public $opt4 = "";
    public $opt5 = "";
    public $opt6 = "";
    public $series_id   = 0; //Серия

    public $order   = 0;      //порядковый номер
    public $file_custom = "";
    public $sorted_by_user = 0;
    public $add_by_user = 0;
    public $checked = 0;
    public $lumfx_abs = 0;
    public $vangl_abs = 0;
    public $is_offer = 0;
}