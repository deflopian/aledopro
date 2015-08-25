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
        $sheet = $objPHPExcel->getActiveSheet();
        $sheet->setTitle($commercial->title);

        $sheet = self::setColumnWidths($sheet);

        $currentRow = 1;
        $currentColumn = 'A';
        $rightColumn = "G";

        $letters = array('', 'A', 'B', 'C', 'D', 'E', 'F', 'G');

        foreach ($commercial->rooms as $roomNum => $room) {

            //заголовок помещения
            $sheet->mergeCells($currentColumn . $currentRow . ":" . $rightColumn . $currentRow);
            $sheet->setCellValue($currentColumn . $currentRow, $room->title);
            $sheet = self::formatHeading($sheet, $currentRow);
            $sheet = self::formatDiapason($sheet, 'A' . $currentRow, 'G' . ($currentRow));
            if ($roomNum == 0) {
                $currentRow++;
                $sheet->getRowDimension($currentRow)->setRowHeight(30);
                $sheet = self::formatDiapason($sheet, 'A' . $currentRow, 'G' . ($currentRow));
                $sheet->setCellValue('A' . $currentRow, "Артикул");
                $sheet->setCellValue('B' . $currentRow, "Наименование");
                $sheet->setCellValue('C' . $currentRow, "Фото");
                $sheet->setCellValue('D' . $currentRow, "Описание");
                $sheet->setCellValue('E' . $currentRow, "Цена, руб.");
                $sheet->setCellValue('F' . $currentRow, "Количество, шт.");
                $sheet->setCellValue('G' . $currentRow, "Сумма, руб.");
            }

            foreach ($room->prods as $pkey => $prod) {
                $currentRow++;

                $sheet->getRowDimension($currentRow)->setRowHeight(200/1.33);
                $sheet = self::formatDiapason($sheet, 'A' . $currentRow, 'G' . ($currentRow));
                $price = CatalogService::getTruePrice($prod->product->price_without_nds);
                $count = $prod->count ? $prod->count : 1    ;

                //попарно мержим строки
                for ($i=1; $i<=7; $i++) {
                    if ($i == 2) continue;
                    $sheet->mergeCells($letters[$i] . $currentRow . ":" . $letters[$i] . ($currentRow+1));
                }

                //в смерженные ячейки пишем данные


                $sheet->setCellValue('A' . $currentRow,  $prod->product->id);

                $sheet->setCellValue('B' . $currentRow, $prod->product->title);

                $sheet->setCellValueExplicit('B' . ($currentRow + 1), "Показать на сайте", \PHPExcel_Cell_DataType::TYPE_STRING2, TRUE)->getHyperlink()->setUrl('http://www.aledo-pro.ru/catalog/product/' . $prod->product->id);

                // Config
                $link_style_array = array(
                    'font'  => array(
                        'color' => array('rgb' => '0000FF'),
                        'underline' => 'single'
                    )
                );
                $sheet->getStyle('B' . ($currentRow + 1))->applyFromArray($link_style_array);

                if ($prod->product->previewName) {
                    $objDrawing = new \PHPExcel_Worksheet_MemoryDrawing();
                    $type = substr(strrchr($prod->product->previewName, '.'), 1);

                    if (in_array($type, array('jpg', 'jpeg', 'png', 'gif'))) {
                        if ($type == 'jpg') {
                            $type = 'jpeg';
                        }

                        $function = 'imagecreatefrom' . $type;
                        $gdImage = $function('http://aledo-pro.ru/images/products/' . $prod->product->previewName);
                        $objDrawing->setName('Product image');
                        $objDrawing->setDescription('Product image');
                        $objDrawing->setImageResource($gdImage);
                        if ($type == 'jpeg') {
                            $mimeType = \PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_JPEG;
                            $objDrawing->setRenderingFunction(\PHPExcel_Worksheet_MemoryDrawing::RENDERING_JPEG);
                        } elseif ($type == 'png') {
                            $mimeType = \PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_PNG;
                            $objDrawing->setRenderingFunction(\PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
                        } else {
                            $mimeType = \PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_GIF;
                            $objDrawing->setRenderingFunction(\PHPExcel_Worksheet_MemoryDrawing::RENDERING_GIF);
                        }

                        $objDrawing->setMimeType($mimeType);
                        $objDrawing->setWidth(150);//200
                        $objDrawing->setCoordinates('C' . $currentRow);
                        $objDrawing->setWorksheet($sheet);
                        $maxWidth = 190;//240
                        $offsetX = ($maxWidth - $objDrawing->getWidth())/2;
                        $objDrawing->setOffsetX($offsetX);
                        $objDrawing->setOffsetY(20);
                    }

                }
				
				$sheet->setCellValue('D' . $currentRow, self::getMainParams($prod));
                $sheet->setCellValue('E' . $currentRow, $price); //number_format($price, 2)
                $sheet->setCellValue('F' . $currentRow, $count);
                $sheet->setCellValue('G' . $currentRow, $price * $count);

                $currentRow++; //компенсируем смерженную строку
            }
            $currentRow++;
        }


        $writer = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        ob_end_clean();
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"results.xlsx\"");
        header("Cache-Control: max-age=0");

        $writer->save("php://output");

        ob_clean();

//        $writer->save("results.xlsx");
    }

    public static function getMainParams($product) {
        $res = "";
        if (is_array($product->mainParams)) {
            foreach ($product->mainParams as $paramKey => $paramVal) {
                if ($product->product->$paramKey) {
                    $res .= $paramVal . " - " . $product->product->$paramKey . "\n";
                }
            }
        }

        return $res;
    }

    /**
     * @param \PHPExcel_Worksheet $sheet
     * @return \PHPExcel_Worksheet
     */
    public static function setColumnWidths($sheet) {

        $sheet->getColumnDimension('A')->setWidth(61/7);
        $sheet->getColumnDimension('B')->setWidth(187/7);
        $sheet->getColumnDimension('C')->setWidth(187/7);
        $sheet->getColumnDimension('D')->setWidth(236/7);
        $sheet->getColumnDimension('E')->setWidth(82/7);
        $sheet->getColumnDimension('F')->setWidth(89/7);
        $sheet->getColumnDimension('G')->setWidth(93/7);

        return $sheet;

    }

    /**
     * @param \PHPExcel_Worksheet $sheet
     * @param integer $rowNum
     * @return \PHPExcel_Worksheet
     */
    public static function formatHeading($sheet, $rowNum) {
        $style = array(
            'font'  => array(
                'bold'  => true,
            )
        );

        $sheet->getStyle('A'.$rowNum)->applyFromArray($style);
        $sheet->getRowDimension($rowNum)->setRowHeight(40);
        return $sheet;

    }

    /**
     * @param \PHPExcel_Worksheet $sheet
     * @param string $begin
     * @param string $end
     * @return \PHPExcel_Worksheet
     */
    public static function formatDiapason($sheet, $begin, $end) {
        $style = array(
            'alignment' => array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
        );

        $sheet->getStyle($begin . ':' . $end)->applyFromArray($style);
        $sheet->getStyle($begin . ':' . $end)->getAlignment()->setWrapText(true);

        return $sheet;

    }
    public static function addImage(&$sheet, $imgName, $cell) {
        if (!$imgName) {
            return "";
        }


        return "";
    }
}