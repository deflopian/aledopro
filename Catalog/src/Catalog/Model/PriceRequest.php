<?php
namespace Catalog\Model;

use Application\Model\SampleModel;

class PriceRequest extends SampleModel
{
    public $id;
    public $section_id;
    public $section_type;
    public $is_requestable;
}