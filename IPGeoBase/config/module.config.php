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
                array('route' => 'developers', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'IPGeoBase\Controller\GeoBanner', 'roles' => array('guest','user') ),
            ),
        ),
    ),
);
