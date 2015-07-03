<?php
namespace Catalog\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Application\Service\MailService;
use Catalog\Mapper\CatalogMapper;
use Catalog\Model\FilterParam;
use Catalog\Model\ParamToSeries;
use Catalog\Model\Series;
use Catalog\Model\SeriesParams;
use Catalog\Service\ElecService;
use Catalog\Service\GMCService;
use Catalog\Service\ProductsAggregator;
use Catalog\Service\YMLService;
use News\Model\News;
use Catalog\Controller\AdminController;
use Reports\Config\ReportConfig;
use Reports\Mapper\ReportMapper;
use Reports\Model\ReportItem;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Catalog\Service\CatalogService;
use Catalog\Service\CronService;
use PHPExcel_Reader_CSV;
use Zend\View\Helper\ViewModel;
use Zend\XmlRpc\Value\String;

class CronController extends BaseController
{

    protected $csvName = 'kgoods.csv';
    protected $prevModifiedPath = 'prevmodif.txt';
    protected $lineCSVFilePath = 'line.txt';
    protected $entityImgName = 'Catalog\Model\SeriesImg';
    protected $url = 'series';

    public static $tables = array(
        AdminController::SERIES_TABLE => 'SeriesTable',
        AdminController::PRODUCT_TABLE => 'ProductTable',
        AdminController::FILTER_BY_SERIES_TABLE => 'SeriesParamsTable',
        AdminController::FILTER_PARAM_TABLE => 'FilterParamTable',
        AdminController::PARAM_TO_SERIES_TABLE => 'ParamToSeriesTable',
    );

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);

        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            $controller->layout('layout/empty');
        }, 100); // execute before executing action logic
    }

    public function myVerySpecialScriptAction() {

        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);
        $cm->getSection(3, true, true, true, array(), false);
        $prodAgg = ProductsAggregator::getInstance();
        $prods = $prodAgg->getProducts();

        $fileTable = $sl->get('FilesTable');
        $num = 0;
        foreach ($prods as $prod) {
            $imgName = $prod->file_custom;
            $file = $fileTable->fetchByCond('uid', $prod->id);

            if (!empty($imgName) && !count($file)) {

                if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/product_docs/' . $imgName) && !file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/products/' . $imgName)) {
                    copy($_SERVER['DOCUMENT_ROOT'] . '/images/product_docs/' . $imgName, $_SERVER['DOCUMENT_ROOT'] . '/images/products/' . $imgName);
                }

                $entity = new File();
                $entity->name = $imgName;
                $entity->type = FileTable::TYPE_IMAGE;
                $entity->real_name = $imgName;
                $entity->path = $imgName;
                $entity->size = 0;
                $entity->timestamp = time(true);
                $entity->uid = $prod->id;

                $fileTable->save($entity);
                $num++;
            }
        }
        return array('num' => $num);
    }

    public function getfileAction() {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return new ViewModel();
        }
//        return array();
        $url = 'http://www.planar.spb.ru/ekdb/kgoods.csv';
        $newPathFile =  $_SERVER['DOCUMENT_ROOT'] . '/' . $this->csvName;
        $prevModifiedPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->prevModifiedPath;
        $linePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->lineCSVFilePath;


        @ $headers = get_headers($url, 1);
        if ($headers === false) {
            echo 'Не удалось достучаться до адреса <a href="' . $url . '">' . $url . '</a>. Хост недоступен. Возможно, следует проверить настройки прокси-сервера?';
            return array();
        }
        $last_modified = strtotime($headers['Last-Modified']);

        if (file_exists($prevModifiedPath)) {
            $prev_modified = file_get_contents( $prevModifiedPath );
        } else {
            $prev_modified = false;
        }

        if (($prev_modified &&
                $prev_modified < $last_modified) ||
            !$prev_modified) {
            $fileContent = file_get_contents($url);

            if ($fileContent !== false) {
                echo 'Файл успешно загружен';
                if (empty($fileContent)) {
                    echo '<br>Файл прискорбно пуст';
                    return array();
                }
            } else {
                echo 'Ошибка при загрузке файла с <a href="http://www.planar.spb.ru/ekdb/kgoods.csv">http://www.planar.spb.ru/ekdb/kgoods.csv</a>';
            }

            $products = $this->getServiceLocator()->get('Catalog\Model\ProductTable')->fetchAllIds();
            $prodIds = array();
            foreach ($products as $product) {
                $prodIds[] = $product['id'];
            }
            $str = implode(',', $prodIds);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/products.txt', $str);

            $result = file_put_contents($newPathFile, $fileContent);
            if ($result !== false) {
                echo 'Файл успешно сохранён на диск в директорию ' . $newPathFile;
            } else {
                echo 'Неизвестная ошибка при сохранении файла в директорию ' . $newPathFile;
            }
            //удаляем весь хлам, оставшийся с прошлого раза

            $this->getServiceLocator()->get('Catalog\Model\ParamToSeriesTable')->truncate();

            //удаляем весь хлам, оставшийся с прошлого раза
            CronService::deleteOldProducts(
                $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::PRODUCT_TABLE]),
                $this->getServiceLocator()->get('Catalog\Model\DopProdTable')
            );
            //сортируем весь хлам, оставшийся с момента прошлой загрузки файла
            CronService::sortOldProducts($this->getServiceLocator());



            file_put_contents($prevModifiedPath, $last_modified);






            if (file_exists($linePath)) {
                unlink( $linePath );
            }
        } else {

        }
        return array();

    }

    public function sortAllProductsAction() {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return new ViewModel();
        }

        $seriesTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
        $allSeries = $seriesTable->fetchAll();
        foreach ($allSeries as $oneser) {
            if (in_array($oneser->subsection_id, array(30,31,32))) {
                CatalogService::makesort('power', $oneser->id, 2, $this->getServiceLocator());
            } else {
                CatalogService::makesort('free_balance', $oneser->id, 2, $this->getServiceLocator());
            }
        }
        return false;
    }

    public function checksessionAction()
    {
        echo date('Y-m-d H:i');
        //var_dump(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/' . $this->lineCSVFilePath));
    }


    /**
     * Разбирает csv-каталог на отдельные продукты, для каждого продукта определяет айди серии,
     * мин-макс параметры внутри каждой серии и возможные значения для каждого параметра
     * подготавливает основу для возможности фильтрации результатов
     *
     * по задумке, вызывается кроном каждые 15 минут
     *
     * @return array
     */
    public function parsexlsAction()
    {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return new ViewModel();
        }
//        return array();
        $insertedSeries = 0;
        $insertedSeriesMinMax = 0;

        $calculatedRows = 0;
        $time = microtime(true);

        $scvPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->csvName;
        $linePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->lineCSVFilePath;
        $productsPath = $_SERVER['DOCUMENT_ROOT'] . '/products.txt';



        if (!file_exists($scvPath)) {
            return array();
        }

        $productsIds = array();
        if (file_exists($productsPath)) {
            $str = file_get_contents($productsPath);
            $productsIdsNon = explode(',', $str);
            foreach ($productsIdsNon as $pid) {
                $productsIds[$pid] = 1;
            }
        }

        //в $productSeries храним текущие диапазоны значений для каждой из серий
        $productSeries = array();
        $reportMapper = ReportMapper::getInstance($this->getServiceLocator());
        if (file_exists($linePath)) {
            $zeroPriceReport = $reportMapper->getLast(ReportMapper::REPORT_TYPE_PRODUCT_ZERO_PRICE);
            $orphanSeriesReport = $reportMapper->getLast(ReportMapper::REPORT_TYPE_ORPHAN_SERIES);
            $newProdsReport = $reportMapper->getLast(ReportMapper::REPORT_TYPE_NEW_PRODUCTS);
        } else {
            $osConfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_ORPHAN_SERIES];
            $zpConfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_PRODUCT_ZERO_PRICE];
            $npConfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_NEW_PRODUCTS];
            $zeroPriceReport = $reportMapper->add($zpConfig["name"], ReportMapper::REPORT_TYPE_PRODUCT_ZERO_PRICE, $zpConfig["text"]);
            $orphanSeriesReport = $reportMapper->add($osConfig["name"], ReportMapper::REPORT_TYPE_ORPHAN_SERIES, $osConfig["text"]);
            $newProdsReport = $reportMapper->add($npConfig["name"], ReportMapper::REPORT_TYPE_NEW_PRODUCTS, $npConfig["text"]);
        }


        $products = CronService::parseXLStoDatabase($scvPath, $linePath, $calculatedRows);

        $filterParamTable = $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::FILTER_PARAM_TABLE]);
        $paramToSeriesTable = $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::PARAM_TO_SERIES_TABLE]);
        $filterParamsArray = BaseController::$discreteFilterParams;
        $allDiapasonParams = BaseController::$diapasonFilterParams;
        //значения с возможными вариантами
        //список возможных значений параметров из $filterParamsArray и максимальный на данный момент айдишник
        list($filterParams, $maxId) = CronService::getParamsVariation($filterParamsArray, $filterParamTable);
        list($allParamsToAllSeries, $maxParamsId) = CronService::getAllParamsToAllSeries($paramToSeriesTable);

        $filterParamsForSave = array();
        $paramToSeriesForSave = array();


        /** @var \Catalog\Model\Product[] $products */
        foreach ($products as $pKey => $product) {

            //диапазоны значений по сериям
            list($productSeries, $product) =
                CronService::diapasonBySeries(      $this->getServiceLocator(),
                                                    $product,
                                                    $productSeries,
                                                    $allDiapasonParams,
                                                    $insertedSeries,
                                                    $insertedSeriesMinMax,
                                                    $reportMapper,
                                                    $orphanSeriesReport

                );
            if ($product->series_id > 0) {
                $product =
                    CronService::fillProductAndParams($allParamsToAllSeries,
                        $filterParamsArray,
                        $product,
                        $filterParams,
                        $maxId,
                        $maxParamsId,
                        $filterParamsForSave,
                        $paramToSeriesForSave
                    );
            }

            if (count($productsIds) > 0 && !array_key_exists($product->id, $productsIds)) {
                if (!$product->id) {
                    var_dump($product);
                } else {
                    $item = new ReportItem();
                    $item->linked_id = $product->id;
                    $item->linked_type = AdminController::PRODUCT_TABLE;
                    $item->report_id = $newProdsReport->id;
                    $item->title = $product->title;
                    $item->url = "/admin/catalog/product/" . $item->linked_id;

                    $newProdsReport = $reportMapper->addItems($newProdsReport, array($item));
                }

            }

//            if ($product->series_id > 0 && ($product->price_without_nds == 0 || is_null($product->price_without_nds))) {
//
//                $item = new ReportItem();
//                $item->linked_id = $product->id;
//                $item->linked_type = AdminController::PRODUCT_TABLE;
//                $item->report_id = $zeroPriceReport->id;
//                $item->title = $product->title;
//                $item->url = "/admin/catalog/product/" . $item->linked_id . "/";
//
//                $zeroPriceReport = $reportMapper->addItems($zeroPriceReport, array($item));
//            }

            $products[$pKey] = $product;

        }


        $seriesIdsRev = array();
        foreach ($products as $key => $oneProd) {
            if (!$oneProd->id) {
                unset($products[$key]);
                continue;
            }
            $oneProd->checked = 1;
            $seriesIdsRev[$oneProd->series_id] = 1;
            $products[$key] = $oneProd->toArray();
        }
        foreach ($filterParamsForSave as $key => $paramEntity) {
            $filterParamsForSave[$key] = $paramEntity->toArray();
        }
        foreach ($paramToSeriesForSave as $key => $paramEntity) {
            $paramToSeriesForSave[$key] = $paramEntity->toArray();
        }

        $insertedParamsValues = count($filterParamsForSave);
        $filterParamTable->saveAll($filterParamsForSave);

        $insertedSeriesParams = count($paramToSeriesForSave);
        $paramToSeriesTable->saveAll($paramToSeriesForSave);

        $insertedRows = count($products);

        $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::PRODUCT_TABLE])->saveAll($products);

        if ($insertedSeries || $insertedRows || $insertedSeriesMinMax || $insertedParamsValues || $insertedSeriesParams) {
            CatalogService::updateLastModified($this->getServiceLocator(), \Info\Service\SeoService::CATALOG_INDEX);
            CatalogService::updateLastModified($this->getServiceLocator(), \Info\Service\SeoService::CATALOG_SERIES);
            CatalogService::updateLastModified($this->getServiceLocator(), \Info\Service\SeoService::CATALOG_SUBSECTION);
            CatalogService::updateLastModified($this->getServiceLocator(), \Info\Service\SeoService::CATALOG_SECTION);
        }

        if (!file_exists($scvPath)) {
//            if (isset($orphanSeriesReport->items) && count($orphanSeriesReport->items) > 0) {
//                list($email, $mailView) = MailService::prepareReportData($this->getServiceLocator(), $orphanSeriesReport);
//                MailService::sendMail($email, $mailView, "Новый отчёт номер " . $orphanSeriesReport->id . " по добавленным сериям");
//            }
//            if (isset($zeroPriceReport->items) && count($zeroPriceReport->items) > 0) {
//                list($email, $mailView) = MailService::prepareReportData($this->getServiceLocator(), $zeroPriceReport);
//                MailService::sendMail($email, $mailView, "Новый отчёт номер " . $zeroPriceReport->id . " по продуктам без цены");
//            }
            if (isset($newProdsReport->items) && count($newProdsReport->items) > 0) {
                list($email, $mailView) = MailService::prepareReportData($this->getServiceLocator(), $newProdsReport);
                MailService::sendMail($email, $mailView, "Отчёт по новым продуктам номер " . $newProdsReport->id);
                MailService::sendMail("deflopian@gmail.com", $mailView, "Отчёт по новым продуктам номер " . $newProdsReport->id);
            } elseif (count($newProdsReport->items) == 0) {
                $reportMapper->delete($newProdsReport->id);
            }
        }

        echo '<br> Работа крона завершена!<br>';
        echo 'обработано строк файла: ' . $calculatedRows . '<br>';
        echo 'добавлено/обновлено продуктов в каталоге: ' . $insertedRows . '<br>';
        echo 'добавлено/обновлено серий в каталоге: ' . $insertedSeries . '<br>';
        echo 'добавлено/обновлено диапазонов значений по сериям: ' . $insertedSeriesMinMax . '<br>';
        echo 'добавлено/обновлено возможных значений параметров: ' . $insertedParamsValues . '<br>';
        echo 'добавлено/обновлено возможных параметров по серии: ' . $insertedSeriesParams . '<br>';
        echo 'затраченное время: ' . (microtime(true) - $time) . ' секунд <br>';

        return array('products' => $products);
    }

    public function removeYMLFileAction()
    {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return $response;
        }

        $linePath = $_SERVER['DOCUMENT_ROOT'] . '/aledo-shop.dtd';

        if (file_exists($linePath)) {
            unlink( $linePath );
        }
        return $this->redirect()->toRoute('zfcadmin/market');
    }

    public function removeGMCFileAction()
    {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return $response;
        }

        $linePath = $_SERVER['DOCUMENT_ROOT'] . '/gmc-aledo.dtd';

        if (file_exists($linePath)) {
            unlink( $linePath );
        }
        return $this->redirect()->toRoute('zfcadmin/market');
    }

    public function makeYMLFileAction()
    {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return $response;
        }
//        return array();
        $prodsInMarket = $this->getProductInMarketTable()->fetchAll();
        $pimIds = array();
        $prodsInMarketById = array();
        foreach ($prodsInMarket as $onePIM) {
            $pimIds[] = $onePIM->id;
            $prodsInMarketById[$onePIM->id] = $onePIM;
        }

        if (count($pimIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        $products = $this->getProductTable()->fetchByConds(array('id' => $pimIds), array('series_id' => 0));
        if (count($products) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        $seriesIds = array();
        foreach ($products as &$oneprod) {
            $oneprod->bid = $prodsInMarketById[$oneprod->id]->bid;
            $oneprod->purchase = $prodsInMarketById[$oneprod->id]->purchase;
            if (!empty($prodsInMarketById[$oneprod->id]->alias)) {
                $oneprod->title = $prodsInMarketById[$oneprod->id]->alias;
            }
            $seriesIds[] = $oneprod->series_id;
        }

        if (count($seriesIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');
        $series = $this->getSeriesTable()->fetchByConds(array('id' => $seriesIds, 'deleted' => 0), array('subsection_id' => 0));
        if (count($series) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        $subsectionsIds = array();
        foreach ($series as $oneser) {
            $subsectionsIds[] = $oneser->subsection_id;
        }



        if (count($subsectionsIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');
        $subsections = $this->getSubSectionTable()->fetchByConds(array('id' => $subsectionsIds, 'deleted' => 0), array('section_id' => 0));
        if (count($subsections) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        $sectionsIds = array();
        foreach ($subsections as $onesubsec) {
            $sectionsIds[] = $onesubsec->section_id;
        }
        if (count($sectionsIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');
        $sections = $this->getSectionTable()->fetchByConds(array('id' => $sectionsIds, 'deleted' => 0));
        if (count($sections) == 0) return  $this->redirect()->toRoute('zfcadmin/market');
        $sectionsIds = array();
        foreach ($sections as $onesec) {
            $sectionsIds[$onesec->id] = $onesec->id;
        }

        $subsectionsIds = array();
        foreach ($subsections as $subsecKey => $onesubsec) {
            if (array_key_exists($onesubsec->section_id, $sectionsIds)) {
                $subsectionsIds[$onesubsec->id] = $onesubsec->id;
            } else {
                unset($subsections[$subsecKey]);
            }
        }

        $seriesIds = array();
        $seriesImgs = array();
        foreach ($series as $serKey => $oneser) {
            if (array_key_exists($oneser->subsection_id, $subsectionsIds)) {
                $seriesIds[$oneser->id] = $oneser->id;
                $seriesImgs[$oneser->id] = $oneser->img;
            } else {
                unset($series[$serKey]);
            }
        }

        foreach ($products as $prodKey => &$oneprod) {
            if (!array_key_exists($oneprod->series_id, $seriesIds)) {
                unset($products[$prodKey]);
            } else {
                $oneprod->series_img = $seriesImgs[$oneprod->series_id];
            }
        }

        $params = $this->getProductParamsTable()->fetchAll();

        $allParams = array();
        foreach($params as $oneparam) {
            $allParams[$oneparam->field] = array(
                'name' => $oneparam->title,
                'unit'  => $oneparam->post_value ? $oneparam->post_value : $oneparam->pre_value
            );
        }

        //return false;
        $ymlFile = YMLService::makeYMLFile($sections, $subsections, $series, $products, $allParams);
        $result = file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/aledo-shop.dtd', $ymlFile);
        return $this->redirect()->toRoute('zfcadmin/market');
    }

    public function makeGMCFileAction()
    {

        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return $response;
        }
//        return array();
        $prodsInMarket = $this->getProductInMarketTable()->fetchAll();

        $pimIds = array();
        $prodsInMarketById = array();
        foreach ($prodsInMarket as $onePIM) {
            $pimIds[] = $onePIM->id;
            $prodsInMarketById[$onePIM->id] = $onePIM;
        }

        if (count($pimIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        $products = $this->getProductTable()->fetchByConds(array('id' => $pimIds), array('series_id' => 0));
        if (count($products) == 0) return  $this->redirect()->toRoute('zfcadmin/market');


        $seriesIds = array();
        foreach ($products as $oneprod) {
            $oneprod->bid = $prodsInMarketById[$oneprod->id]->bid;
            $oneprod->purchase = $prodsInMarketById[$oneprod->id]->purchase;
            $seriesIds[] = $oneprod->series_id;
        }

        if (count($seriesIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');
        $series = $this->getSeriesTable()->fetchByConds(array('id' => $seriesIds), array('subsection_id' => 0));

        //$seriesIds = array();
        $seriesImgs = array();
        foreach ($series as $serKey => $oneser) {
            $seriesImgs[$oneser->id] = $oneser->img;

        }

        if (count($series) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        foreach ($products as $prodKey => &$oneprod) {
            if (!in_array($oneprod->series_id, $seriesIds)) {
                unset($products[$prodKey]);
            } else {
                $oneprod->series_img = $seriesImgs[$oneprod->series_id];
            }
        }

        $gmcFile = GMCService::makeGMCFile($series, $products);
        $result = file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/gmc-aledo.xml', $gmcFile);

        return $this->redirect()->toRoute('zfcadmin/market');
    }

    public function makeElecFileAction()
    {

        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return $response;
        }
//        return array();
        $prodsInMarket = ElecService::$groups;
        $pimIds = array();
        $prodsInMarketById = array();
        $products = array();
        foreach ($prodsInMarket as $key => $group) {
            $prs = $this->getProductTable()->fetchByConds(array('id' => array_keys($group)), array('series_id' => 0));
            foreach ($prs as $pr) {
                $products[$key][$pr->id] = $pr;
            }
        }

        if (count($products) == 0) return  $this->redirect()->toRoute('zfcadmin/market');


        $seriesIds = array();
        foreach ($products as $prs) {
            foreach ($prs as $oneprod ) {
                $seriesIds[] = $oneprod->series_id;
            }
        }

        if (count($seriesIds) == 0) return  $this->redirect()->toRoute('zfcadmin/market');
        $series = $this->getSeriesTable()->fetchByConds(array('id' => $seriesIds), array('subsection_id' => 0));

        //$seriesIds = array();
        $seriesImgs = array();
        foreach ($series as $oneser) {
            $seriesImgs[$oneser->id] = $oneser->img;
        }

        if (count($series) == 0) return  $this->redirect()->toRoute('zfcadmin/market');

        foreach ($products as $groupKey => &$prs) {
            foreach ($prs as $prodKey => &$oneprod) {
                if (!in_array($oneprod->series_id, $seriesIds)) {
                    unset($products[$prodKey]);
                } else {
                    if (isset($prodsInMarket[$groupKey][$oneprod->id]['img'])
                        && $prodsInMarket[$groupKey][$oneprod->id]['img']) {
                        $oneprod->series_img = $prodsInMarket[$groupKey][$oneprod->id]['img'];
                    } else {
                        $oneprod->series_img = 'http://aledo-pro.ru/images/series/' . $seriesImgs[$oneprod->series_id];
                    }
                }
            }
        }

        $gmcFile = ElecService::makeElecFile($products);
        $result = file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/elec-aledo.xml', $gmcFile);

        return $this->redirect()->toRoute('zfcadmin/market');
    }
}