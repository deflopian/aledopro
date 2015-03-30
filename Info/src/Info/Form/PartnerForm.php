<?php
namespace Info\Form;

use Application\Service\ApplicationService;
use Services\Controller\ServicesController;
use Zend\InputFilter;
use Zend\Form\Form;
use Zend\Form\Element;
use Zend\Validator;

class PartnerForm extends Form
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->addElements();
        $this->setInputFilter($this->createInputFilter());
    }

    public function addElements()
    {
        $textFields = $this->getTextFields();

        foreach($textFields as $name=>$required){
            $text = new Element\Text($name);
            $this->add($text);
        }
    }

    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

        $messages = ApplicationService::getValidationFormMessages();
        $textFields = $this->getTextFields();

        foreach($textFields as $name=>$required){
            $inputFilter->add(array(
                'name' => $name,
                'required' => true,
                'validators' => $messages['text'],
                'filters' => array(
                    array('name' => 'stringtrim'),
                    array('name' => 'striptags')
                )
            ));
        }

        return $inputFilter;
    }

    private function getTextFields()
    {
        return array(
            'name' => true,
            'activity' => true,
            'job' => false,
            'phone' => true,
            'email' => true,
            'company_name' => true,
            'company_activity' => true,
            'brands_sample' => true,
            'post_index' => false,
            'city' => false,
            'adress' => false,
            'company_phone' => true,
            'company_fax' => false,
            'company_email' => true,
            'company_website' => true,
        );
    }
}