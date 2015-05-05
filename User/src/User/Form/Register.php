<?php

namespace User\Form;

use Zend\Form\Element\Captcha as Captcha;
use ZfcUser\Options\RegistrationOptionsInterface;

class Register extends Base
{
    protected $captchaElement= null;

    /**
     * @var RegistrationOptionsInterface
     */
    protected $registrationOptions;

    /**
     * @param string|null $name
     * @param RegistrationOptionsInterface $options
     */
    public function __construct($name = null, RegistrationOptionsInterface $options)
    {
        $this->setRegistrationOptions($options);
        parent::__construct($name);


        $this->remove('userId');


        $this->add(array(
            'name' => 'user_tel',
            'options' => array(
                'label' => 'Телефон',
            ),
            'attributes' => array(
                'type' => 'text'
            ),
        ));

        $this->add(array(
            'name' => 'user_city',
            'options' => array(
                'label' => 'Город',
            ),
            'attributes' => array(
                'type' => 'text'
            ),
        ));

        $this->add(array(
            'name' => 'user_is_spamed',
            'type' => 'Zend\Form\Element\Checkbox',
            'options' => array(
                'label' => 'Подписка на новости',
            ),
            'attributes' => array(
                'type' => 'Zend\Form\Element\Checkbox'
            ),
        ));

        $this->get('submit')->setLabel('Register');
        $this->getEventManager()->trigger('init', $this);
    }

    /**
     * Set Regsitration Options
     *
     * @param RegistrationOptionsInterface $registrationOptions
     * @return Register
     */
    public function setRegistrationOptions(RegistrationOptionsInterface $registrationOptions)
    {
        $this->registrationOptions = $registrationOptions;
        return $this;
    }

    /**
     * Get Regsitration Options
     *
     * @return RegistrationOptionsInterface
     */
    public function getRegistrationOptions()
    {
        return $this->registrationOptions;
    }
}
