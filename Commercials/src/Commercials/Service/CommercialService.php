<?php
namespace Commercials\Service;

use Catalog\Controller\CronController;
use Catalog\Service\CatalogService;
use Commercials\Model\Commercial;
use Zend\Session\Container;

class CommercialService {
    /**
     * @param $commercial Commercial
     */
    public static function makeCommercialXls($commercial, $userName = false)
    {
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()
            ->setCreator("Aledo Pro");

        if ($userName) {
            $objPHPExcel->getProperties()
                ->setLastModifiedBy("Vadim Bannov");
        }
        $sheet = $objPHPExcel->getSheet(0);
        $sheet->setTitle($commercial->title);

        $currentRow = 1;
        $currentColumn = 'A';
        $rightColumn = "G";

        $letters = array('', 'A', 'B', 'C', 'D', 'E', 'F', 'G');

        foreach ($commercial->rooms as $roomNum => $room) {

            //заголовок помещения
            $sheet->mergeCells($currentColumn . $currentRow . ":" . $rightColumn . $currentRow);
            $sheet->setCellValue($currentColumn . $currentRow, $room->title);
            

            if ($roomNum == 0) {
                $currentRow++;
                $sheet->setCellValue('A' . $currentRow, "Артикул");
                $sheet->setCellValue('B' . $currentRow, "Наименование");
                $sheet->setCellValue('C' . $currentRow, "Фото");
                $sheet->setCellValue('D' . $currentRow, "Описание");
                $sheet->setCellValue('E' . $currentRow, "Цена");
                $sheet->setCellValue('F' . $currentRow, "Количество");
                $sheet->setCellValue('G' . $currentRow, "Сумма");
            }





            foreach ($room->prods as $prod) {
                $currentRow++;
                $sheet->getRowDimension($currentRow)->setRowHeight(200);

                $price = CatalogService::getTruePrice($prod->product->price_without_nds);
                $count = $prod->product->free_balance;

                //попарно мержим строки
                for ($i=1; $i<=7; $i++) {
                    if ($i == 2) continue;
                    $sheet->mergeCells($letters[$i] . $currentRow . ":" . $letters[$i] . ($currentRow+1));
                }

                //в смерженные ячейки пишем данные
                $sheet->setCellValue('A' . $currentRow, $prod->product->id);
                $sheet->setCellValue('B' . $currentRow, $prod->product->title);
                $sheet->setCellValue('B' . ($currentRow + 1), "Показать на сайте");
                $sheet->getCellByColumnAndRow('B', ($currentRow + 1))->getHyperlink()->setUrl('http://www.aledo-pro.ru/catalog/product/' . $prod->product->id);
//                self::addImage($sheet, $prod->product->previewName, 'C' . $currentRow);

                $objDrawing = new \PHPExcel_Worksheet_MemoryDrawing();

                $gdImage = imagecreatefromjpeg('http://stage.aledo-pro.ru/images/products/' . $prod->product->previewName);
                $objDrawing->setName('Product image');
                $objDrawing->setDescription('Product image');
                $objDrawing->setImageResource($gdImage);
                $objDrawing->setRenderingFunction(\PHPExcel_Worksheet_MemoryDrawing::RENDERING_JPEG);
                $objDrawing->setMimeType(\PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                $objDrawing->setWidth(200);
                $objDrawing->setCoordinates('C' . $currentRow);
                $objDrawing->setWorksheet($sheet);


                $sheet->setCellValue('D' . $currentRow, self::getMainParams($prod));
                $sheet->setCellValue('E' . $currentRow, $price);
                $sheet->setCellValue('F' . $currentRow, $count);
                $sheet->setCellValue('G' . $currentRow, ($price * $count) . " руб.");

                $currentRow++; //компенсируем смерженную строку
            }
            $currentRow++;
        }

        $writer = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');


        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"results.xlsx\"");
        header("Cache-Control: max-age=0");
        $writer->save("php://output");
        ob_clean();
//        $writer->save('output.xlsx');
    }

    public static function getMainParams($product) {
        $res = "";
        foreach ($product->mainParams as $paramKey => $paramVal) {
            if ($product->product->$paramKey) {
                $res .= $paramVal . " - " . $product->product->$paramKey . "\n";
            }
        }
        return $res;
    }
    public static function addImage(&$sheet, $imgName, $cell) {
        if (!$imgName) {
            return "";
        }


        return "";
    }
}