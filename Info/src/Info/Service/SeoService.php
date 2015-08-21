<?php
namespace Info\Service;

use Zend\View\Model\ViewModel;

class SeoService {

    const CATALOG_SECTION = 1;
    const CATALOG_SUBSECTION = 2;
    const CATALOG_SERIES = 3;
    const BLOG = 4;
    const NEWS = 5;
    const INFO = 6;
    const PROJECTS = 7;
    const SERVICES = 8; //нет подходящих экшенов
    const OFFERS = 9;
    const VACANCIES = 10;
    const TEAM = 11;
    const CONTACTS = 12;
    const CART = 13;
    const CATALOG_INDEX = 14;
    const INDEX = 15;
    const SOLUTIONS = 16;
    const ARTICLES = 17;
    const ABOUT = 18;
    const PLUSES = 19;
    const GUARANTEE = 20;
    const JOB = 21;
    const FILES = 22;
    const DELIVERY = 23;
    const PARTNERS = 24;
    const ARTICLE_BLOCKS = 25;
    const DEVELOPERS = 26;
    const IPGEOBASE = 27;


    public static function renderSeoForm($sl, $seoData)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('info/admin/part/seo-form')
            ->setVariables(array( 'seoData' => $seoData ));

        return $sl->get('viewrenderer')->render($htmlViewPart);
    }
}