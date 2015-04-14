<?php
namespace Solutions\Model;

use Application\Model\SampleModel;

class Solution extends SampleModel
{
    /**
     * @var $img_name
     * @var $light_img_name
     * @var $compare_img_1_name
     * @var $compare_img_2_name
     * @var $diagram_1_name
     * @var $diagram_2_name
     * @var $diagram_3_name
     */
    public $id;
    public $title;
    public $text;
    public $img;
    public $preview;
    public $order;
    public $seo_title;
    public $seo_text;

    //какой светильник использовался
    public $light_name;
    public $light_link;
    public $light_img;

    //характеристики используемого светильника
    public $attr_equiv;
    public $attr_effectiveness;
    public $attr_lux_value;
    public $attr_electric_power;
    public $attr_power;
    public $attr_weight;
    public $attr_color_temperature;
    public $attr_cri;
    public $attr_construction;


    public $compare_header;
    //сравнение светильников
    public $compare_name_1;
    public $compare_link_1;
    public $compare_img_1;
    public $compare_power_kwh_1;
    public $compare_lifetime_1;
    public $compare_color_index_1;
    public $compare_electric_power_1;
    public $compare_exposition_1;

    public $compare_name_2;
    public $compare_link_2;
    public $compare_img_2;
    public $compare_power_kwh_2;
    public $compare_lifetime_2;
    public $compare_color_index_2;
    public $compare_electric_power_2;
    public $compare_exposition_2;

    //ключевые преимущества
    public $key_advantage_first;
    public $key_advantage_second;
    public $key_advantage_third;
    public $key_advantage_fourth;

    //графики, диаграммы
    public $diagram_1;
    public $diagram_2;
    public $diagram_3;

    //дополнительные поля
    public $comment_1;
    public $comment_2;
}