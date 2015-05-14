<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:05
 */
return array(
    'controllers' => array(
        'invokables' => array(
            'CommercialController' => 'Commercials\Controller\CommercialController',
        ),
    ),
    'router' => array(
        'routes' => array(
            'commercials' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/commercials[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'CommercialController',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'commercials' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);