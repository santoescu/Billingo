<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfMerger
{
    /**
     * Une varios PDF en uno solo, conservando el tamaño y orientación de
     * cada página. Sirve para armar reportes largos: DomPDF guarda todas
     * las celdas de la tabla en memoria, así que con miles de filas en un
     * solo documento agota la memoria (error fatal que ni siquiera se
     * puede capturar) -- se renderiza por partes y se unen acá.
     *
     * @param  array<int, string>  $pdfs  Contenido binario de cada PDF, en orden.
     * @return string Contenido del PDF unido.
     */
    public static function merge(array $pdfs): string
    {
        if (count($pdfs) === 1) {
            return $pdfs[0];
        }

        $merged = new Fpdi();
        $merged->SetAutoPageBreak(false);

        foreach ($pdfs as $pdf) {
            $pageCount = $merged->setSourceFile(StreamReader::createByString($pdf));

            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);
                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('S');
    }
}
