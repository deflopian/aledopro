<?php
namespace Application\Model;

class SampleModel
{
    public function toArray()
    {
        return get_object_vars($this);
    }

    public function exchangeArray($data)
    {
        $attributes = $this->toArray();

        foreach($attributes as $key=>$val){
            $this->$key = (isset($data[$key])) ? $data[$key] : null;
        }
    }
}