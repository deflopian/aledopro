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
                'required' => $required,
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
            'partner_lastname' => false,
            'partner_name' => true,
            'partner_fathername' => false,
            'partner_city' => false,
            'partner_tel' => false,
            'partner_email' => true,
            'partner_company_name' => true,
            'partner_job_title' => false,
            'partner_scope' => true,
            'partner_brands' => true,
            'partner_office_tel' => true,
            'partner_website' => false,
        );
    }
}