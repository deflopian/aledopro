<?php
namespace Catalog\Service;

use Catalog\Controller\AdminController;
use Catalog\Model\Product;
use Catalog\Model\SeriesTable;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Zend\Session\Container;
use Zend\XmlRpc\Value\String;
use ZendTest\XmlRpc\Server\Exception;

class CronService {
    const SERIES_COLUMN = 50;
//    const LIMIT_PRODUCTS_PER_TIME = 1;
    const LIMIT_PRODUCTS_PER_TIME = 500;

    private static $tables = array(
        AdminController::SECTION_TABLE => 'SectionTable',
        AdminController::SUBSECTION_TABLE => 'SubSectionTable',
        AdminController::SERIES_TABLE => 'SeriesTable',
        AdminController::PRODUCT_TABLE => 'ProductTable',
        AdminController::DOC_TABLE => 'SeriesDocTable',
    );

    private static $unEditableParams = array(
        'id', 'section_id', 'subsection_id', 'series_id', 'order'
    );

    public static function getTableName($id)
    {
        return isset(self::$tables[$id]) ? self::$tables[$id] : null;
    }

    public static function getUnEditableParams()
    {
        return self::$unEditableParams;
    }

    /**
     * парсит строку каталога в продукт
     * @param string $row
     * @return bool|Product
     */
    private static function parseCSVRowToProduct($row)
    {
        $product = new Product();
        $cells = str_getcsv($row, ";");

        //порядок свойств в Product важен!
        $vars = get_object_vars($product);
        $key_vars = array_keys($vars);

        if (count($vars) >= (count($cells))) {
            $shit = 0; //ячейки без значения

            foreach ($cells as $num => $value) {

                if ($value == '##' || $value == '#') {
                    $shit++;
                    continue;
                }
                $vars[$key_vars[$num-$shit]] = trim($value, "^~");

            }

            $product->exchangeArray($vars);
        } else {
            echo 'error';
            return false;
        }


        return $product;
    }


    /**
     * важно: если в файле встречаются битые ссылки, пропускает эти строки
     * @param string $filePath путь к файлу-каталогу
     * @param string $linePath текущее смещение указателя в файле
     * @param int $calculatedRows
     * @return Product[] $products
     */
    public static function parseXLStoDatabase($filePath, $linePath, &$calculatedRows)
    {
        $products = array();
        $offset = 0; //first row is a title

        //get pointer position from session or set new session if not exist

        if (file_exists($linePath)) {
            $offset = file_get_contents($linePath);
        } else {
            file_put_contents($linePath, $offset);
        }

        $handler = fopen($filePath, 'r');

        if (!$handler) return array();

        fseek($handler, $offset);
        $i = 0;
        while(!feof($handler) && ($i++ < self::LIMIT_PRODUCTS_PER_TIME)) {
            $row = fgets($handler);
            $row = iconv('cp1251', 'utf-8', $row);
            $row = trim($row, "#,\n\r");

            if ($offset == 0) {
                $offset += feof($handler) + 1;
                continue;
            }
            $calculatedRows++;
            if (($newprod = self::parseCSVRowToProduct($row)) !== false) {
                $products[] = $newprod;
            }
        }


        if (feof($handler)) {
            if (!@unlink($linePath)) {
                echo "Не удалось удалить файл " . $linePath . ". Файл отсутствует или доступ к нему запрещён";
            }
            if (!@unlink($filePath)) {//if file doesn't exist, other request from cron will be fast and light
                echo "Не удалось удалить файл " . $filePath . ". Файл отсутствует или доступ к нему запрещён";
            }

        } else {
            file_put_contents($linePath, ftell($handler)); //set pointer position to a file
        }
        fclose($handler);
        if (file_exists($linePath)) {
            echo file_get_contents($linePath);
        }
        return $products;
    }

    /**
     * Возвращает из базы возможные значения параметров для фильтра продуктов
     * @param array $filterParamsArray
     * @param \Catalog\Model\FilterParamTable $filterParamTable
     * @return array
     */
    public static function getParamsVariation($filterParamsArray, \Catalog\Model\FilterParamTable $filterParamTable) {
        $result = array();
        $maxId = 0;



        foreach ($filterParamsArray as $filterParam) {
            $variants = $filterParamTable->fetchByCond('field', $filterParam);
            $result[$filterParam] = array();
            foreach ($variants as $variant) {
                $result[$filterParam][$variant->value] = $variant;
                if ($variant->id > $maxId) {
                    $maxId = $variant->id;
                }
            }
        }

        return array($result, $maxId);
    }

    /**
     * Возвращает из базы возможные значения параметров для фильтра продуктов
     * @param \Catalog\Model\ParamToSeriesTable $paramToSeriesTable
     * @return array
     */
    public static function getAllParamsToAllSeries(\Catalog\Model\ParamToSeriesTable $paramToSeriesTable) {
        $result = array();
        $maxId = 0;

        $variants = $paramToSeriesTable->fetchAll();

        foreach ($variants as $variant) {

            $result[$variant->series_id][] = $variant->param_id;
            if ($variant->id > $maxId) {
                $maxId = $variant->id;
            }
        }

        return array($result, $maxId);
    }
}