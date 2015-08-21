<?php
namespace Vacancies\Form;

use Application\Service\ApplicationService;
use Zend\InputFilter;
use Zend\Validator;
use Zend\Form\Form;
use Zend\Form\Element;

class VacancyForm extends Form
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->addElements();
        $this->setInputFilter($this->createInputFilter());
    }

    public function addElements()
    {
        $file = new Element\File('file');
        $file
            ->setLabel('Резюме')
            ->setAttributes(array(
                'id' => 'file',
            ));
        $this->add($file);

        $textFields = $this->getTextFields();

        foreach ($textFields as $name) {
            $text = new Element\Text($name);
            $this->add($text);
        }
    }

    public function addVacancyElement($formVacancies)
    {
        $this->add(array(
            'type' => 'Zend\Form\Element\Select',
            'name' => 'vacancy',
            'options' => array(
                'label' => 'Вакансия',
                'value_options' => $formVacancies,
            ),
        ));
    }


    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

        $messages = ApplicationService::getValidationFormMessages();

        // todome: Не знаю, как приделать сообщения об ошибках к файлу. в Factory нотации не знаю, как создать валидаторы для файла

        $file = new InputFilter\FileInput('file');
        $file->setRequired(true);
        $file->getValidatorChain()
            ->attach(new Validator\File\UploadFile())
            ->attach(new Validator\File\Extension(array('doc', 'txt', 'pdf')))
            //todome: подумать над форматами и безопасностью!!!!

            ->attachByName('filesize', array('max' => 2000000));
        $inputFilter->add($file);

        $textFields = $this->getTextFields();
        foreach ($textFields as $name) {
            $inputFilter->add(array(
                'name' => $name,
                'required' => true,
                'validators' => ($name == 'mail') ? array(array('name' => 'EmailAddress')) : $messages['text'],
                'filters' => array(
                    array('name' => 'stringtrim'),
                    array('name' => 'striptags')
                )
            ));
        }

        $vacancy = new InputFilter\Input('vacancy');
        $vacancy->setRequired(false);
        $inputFilter->add($vacancy);

        $vacancy = new InputFilter\Input('custom_vacancy');
        $vacancy->setRequired(false);
        $inputFilter->add($vacancy);

        $letter = new InputFilter\Input('letter');
        $letter->setRequired(false);
        $inputFilter->add($letter);

        return $inputFilter;
    }

    private function getTextFields()
    {
        return array(
            'name', 'mail'
        );
    }
}