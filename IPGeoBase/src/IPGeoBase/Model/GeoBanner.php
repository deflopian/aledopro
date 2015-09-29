<?php
namespace IPGeoBase\Model;

use Application\Model\SampleModel;

class GeoBanner extends SampleModel
{
    public $id;
    public $region_id;      //���� �����-������ ������� �� ��������� ���� �������, ������������ �������� ����� ��������
    public $region_code;    //��� ������, null ���������� ��� ������
    public $country_code;   //������ ����������� ������������ ������. � ���� ��� RU
    public $section_type;   //������, ��������� ��� �����
    public $section_id;
    public $title;
    public $text;
    public $img;
    public $order;
}