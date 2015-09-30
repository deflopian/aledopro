<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'IPGeoBase\Controller\GeoBanner' => 'IPGeoBase\Controller\GeoBannerController',
            'geobanners' => 'IPGeoBase\Controller\GeoBannerController',
        ),
    ),

    'router' => array(
        'routes' => array(
			'geobanners' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/geobanners[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'IPGeoBase\Controller\GeoBanner',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'geobanners' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'geobanners', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'IPGeoBase\Controller\GeoBanner', 'roles' => array('guest','user') ),
            ),
        ),
    ),
);
