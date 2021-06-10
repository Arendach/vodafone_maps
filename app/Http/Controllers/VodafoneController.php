<?php

namespace App\Http\Controllers;

use DOMDocument;
use DOMElement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use ZipArchive;
use Exception;

class VodafoneController extends Controller
{
    private $nameFile = 'cover_maps';
    private $extensionFile = 'kml';
    private $prettyPrint = false;
    private $output;

    // стилі
    private $lineColor = '461400FF';
    private $lineWidth = 1;
    private $polygonWidth = 1;
    private $polygonFill = 1;
    private $polygonOutline = 1;
    private $polygonColor = '461400FF';


    public function sectionIndex(): View
    {
        return view('welcome');
    }

    public function actionMerge(Request $request)
    {
        ini_set('memory_limit', -1);

        $this->nameFile = $request->get('name_file', $this->nameFile);
        $this->prettyPrint = $request->get('pretty_print', $this->prettyPrint);
        $this->lineColor = $request->get('line_color', $this->lineColor);
        $this->lineWidth = $request->get('line_width', $this->lineWidth);
        $this->polygonWidth = $request->get('polygon_width', $this->polygonWidth);
        $this->polygonFill = $request->get('polygon_fill', $this->polygonFill);
        $this->polygonOutline = $request->get('polygon_outline', $this->polygonOutline);
        $this->polygonColor = $request->get('polygon_color', $this->polygonColor);
        if ($request->get('toArchive')) {
            $this->extensionFile = 'kmz';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        // Creates the root KML element and appends it to the root document.
        $node = $dom->createElementNS('http://earth.google.com/kml/2.1', 'kml');
        $parNode = $dom->appendChild($node);

        // Creates a KML Document element and append it to the KML element.
        $dnode = $dom->createElement('Document');
        $docNode = $parNode->appendChild($dnode);

        // common style
        $style = $dom->createElement('Style');
        $style->setAttribute('id', 'main');

        ///// line style /////
        $lineStyle = $dom->createElement('LineStyle');

        // line style -> color
        $colorLine = $dom->createElement('color');
        $colorLine->appendChild($dom->createTextNode($this->lineColor));
        $lineStyle->appendChild($colorLine);

        // line style -> width
        $widthLine = $dom->createElement('width');
        $widthLine->appendChild($dom->createTextNode($this->lineWidth));
        $lineStyle->appendChild($widthLine);

        ///// polygon style /////
        $stylePoly = $dom->createElement('PolyStyle');

        // polygon style -> fill
        $fillPoly = $dom->createElement('fill');
        $fillPoly->appendChild($dom->createTextNode($this->polygonFill));
        $stylePoly->appendChild($fillPoly);

        // polygon style -> outline
        $outlinePoly = $dom->createElement('outline');
        $outlinePoly->appendChild($dom->createTextNode($this->polygonOutline));
        $stylePoly->appendChild($outlinePoly);

        // polygon style -> color
        $colorPoly = $dom->createElement('color');
        $colorPoly->appendChild($dom->createTextNode($this->polygonColor));
        $stylePoly->appendChild($colorPoly);

        $style->appendChild($lineStyle);
        $style->appendChild($stylePoly);
        $docNode->appendChild($style);

        $words = [];
        // Iterates through the MySQL results, creating one Placemark for each row.
        /** @var $file UploadedFile */
        foreach ($request->kmls as $file) {
            $content = xml_to_array($file->get());

            $placeMarks = isset($content['Folder']) ? $content['Folder']['Placemark'] : $content['Document']['Placemark'];
            foreach ($placeMarks as $placeMark) {
                if (!isset($placeMark['Polygon']) && !isset($placeMark['MultiGeometry'])) {
                    continue;
                }

                // placemark
                $node = $dom->createElement('Placemark');
                $place = $docNode->appendChild($node);

                // placemark -> name
                $nameNode = $dom->createElement('name');
                $nameNode->appendChild($dom->createCDATASection(''));
                $place->appendChild($nameNode);

                // placemark -> description
                $descNode = $dom->createElement('description');
                $cleanDescription = preg_replace('~[\s\n\t]+~', " ", $placeMark['description'] ?? '');
                // $descNode->appendChild($dom->createCDATASection($cleanDescription));
                $descNode->appendChild($dom->createCDATASection(''));
                $place->appendChild($descNode);

                // placemark -> style url
                $styleUrl = $dom->createElement('styleUrl', "#main");
                $place->appendChild($styleUrl);

                if (isset($placeMark['Polygon'])) {
                    $this->createPolygon($dom, $place, $placeMark);
                } else {
                    $this->createMultiGeometry($dom, $place, $placeMark);
                }
            }
        }

        $dom->formatOutput = $this->prettyPrint;

        $this->output = $dom->saveXML();

        if ($this->extensionFile == 'kml') {
            return $this->convertToKML();
        } elseif ($this->extensionFile == 'kmz') {
            return $this->convertToKMZ();
        } else {
            return response(null, 500);
        }
    }

    /**
     * @param DOMDocument $dom
     * @param DOMElement $place
     * @param array $placeMark
     */
    private function createPolygon(&$dom, &$place, $placeMark)
    {
        // placemark -> polygon
        $polygon = $dom->createElement('Polygon');
        $place->appendChild($polygon);

        $this->createPolygonData($dom, $polygon);

        // placemark -> polygon -> outerBoundaryIs
        $outerBoundaryIs = $dom->createElement('outerBoundaryIs');
        $polygon->appendChild($outerBoundaryIs);

        // placemaek -> polygon -> outerBoundaryIs -> LinearRing
        $linearRing = $dom->createElement('LinearRing');
        $outerBoundaryIs->appendChild($linearRing);

        $coordinates = $placeMark['Polygon']['outerBoundaryIs']['LinearRing']['coordinates'];
        $coordinates = preg_replace('~[\s\n\t]+~', " ", $coordinates);
        $coordinatesNode = $dom->createElement('coordinates', $coordinates);
        $linearRing->appendChild($coordinatesNode);

        if (isset($placeMark['Polygon']['innerBoundaryIs'])) {
            foreach ($placeMark['Polygon']['innerBoundaryIs'] as $innerBoundary) {
                // placemark -> MultiGeometry -> Polygon -> innerBoundaryIs
                $innerBoundaryIs = $dom->createElement('innerBoundaryIs');
                $polygon->appendChild($innerBoundaryIs);

                // placemark -> MultiGeometry -> Polygon -> innerBoundaryIs -> LinearRing
                $linearRingNode = $dom->createElement('LinearRing');
                $innerBoundaryIs->appendChild($linearRingNode);

                if (isset($innerBoundary['LinearRing'])) {
                    // placemark -> MultiGeometry -> Polygon -> outerBoundaryIs -> LinearRing -> coordinates
                    $coordinates = $innerBoundary['LinearRing']['coordinates'];
                    $coordinates = preg_replace('~[\s\n\t]+~', " ", $coordinates);
                    $coordinatesNode = $dom->createElement('coordinates', $coordinates);
                    $linearRingNode->appendChild($coordinatesNode);
                } else {
                    // echo 'not found';
                    //dump($polygon);
                }
            }
        } else {
            // dump($polygon);
        }
    }

    /**
     * @param DOMDocument $dom
     * @param DOMElement $place
     * @param array $placeMark
     */
    private function createMultiGeometry(&$dom, &$place, $placeMark)
    {

        // placemark -> MultiGeometry
        $multiGeometry = $dom->createElement('MultiGeometry');
        $place->appendChild($multiGeometry);

        foreach ($placeMark['MultiGeometry']['Polygon'] as $polygon) {
            // placemark -> MultiGeometry -> Polygon
            $polygonNode = $dom->createElement('Polygon');
            $multiGeometry->appendChild($polygonNode);

            $this->createPolygonData($dom, $polygonNode);

            // placemark -> MultiGeometry -> Polygon -> outerBoundaryIs
            $outerBoundaryIsNode = $dom->createElement('outerBoundaryIs');
            $polygonNode->appendChild($outerBoundaryIsNode);

            // placemark -> MultiGeometry -> Polygon -> outerBoundaryIs -> LinearRing
            $linearRingNode = $dom->createElement('LinearRing');
            $outerBoundaryIsNode->appendChild($linearRingNode);

            // placemark -> MultiGeometry -> Polygon -> outerBoundaryIs -> LinearRing -> coordinates
            $coordinates = $polygon['outerBoundaryIs']['LinearRing']['coordinates'];
            $coordinates = preg_replace('~[\s\n\t]+~', " ", $coordinates);
            $coordinatesNode = $dom->createElement('coordinates', $coordinates);
            $linearRingNode->appendChild($coordinatesNode);

            if (isset($polygon['innerBoundaryIs'])) {
                foreach ($polygon['innerBoundaryIs'] as $innerBoundary) {
                    // placemark -> MultiGeometry -> Polygon -> innerBoundaryIs
                    $innerBoundaryIs = $dom->createElement('innerBoundaryIs');
                    $polygonNode->appendChild($innerBoundaryIs);

                    // placemark -> MultiGeometry -> Polygon -> innerBoundaryIs -> LinearRing
                    $linearRingNode = $dom->createElement('LinearRing');
                    $innerBoundaryIs->appendChild($linearRingNode);

                    if (isset($innerBoundary['LinearRing'])) {
                        // placemark -> MultiGeometry -> Polygon -> outerBoundaryIs -> LinearRing -> coordinates
                        $coordinates = $innerBoundary['LinearRing']['coordinates'];

                        // $coordinates = preg_replace('~[\s\n\t]+~', " ", $coordinates);
                        $coordinatesNode = $dom->createElement('coordinates', $coordinates);
                        $linearRingNode->appendChild($coordinatesNode);
                    }
                }
            }
        }
    }

    /**
     * @param DOMDocument $dom
     * @param DOMElement $polygon
     */
    private function createPolygonData(&$dom, &$polygon)
    {
        // placemark -> polygon -> extrude
        $extrude = $dom->createElement('extrude', 1);
        $polygon->appendChild($extrude);

        // placemark -> polygon -> altitudeMode
        $altitudeMode = $dom->createElement('altitudeMode', 'clampToGround');
        $polygon->appendChild($altitudeMode);

        // placemark -> polygon -> altitudeMode
        $tessellate = $dom->createElement('tessellate', 1);
        $polygon->appendChild($tessellate);

    }

    private function convertToKMZ()
    {
        $zip = new ZipArchive();
        $filename = $this->getFilePath();

        if ($zip->open($filename, ZipArchive::CREATE) !== true) {
            throw new Exception("Невозможно открыть <$filename>\n");
        }

        file_put_contents(public_path('coverage_maps/temp.kml'), $this->output);

        $zip->addFromString(public_path('coverage_maps/temp.kml'), "$this->nameFile.kml");

        $zip->close();

        return response()->download($this->getFilePath(), "$this->nameFile.$this->extensionFile", [
            'Content-Type' => 'application/vnd.google-earth.kmz'
        ]);
    }

    private function convertToKML()
    {
        $this->output = preg_replace('~[\s\n\t]+~', ' ', $this->output);

        file_put_contents($this->getFilePath(), $this->output);

        return response()->download($this->getFilePath(), "$this->nameFile.$this->extensionFile", [
            'Content-Type' => 'application/vnd.google-earth.kml+xml'
        ]);
    }

    private function getFilePath()
    {
        return public_path("$this->nameFile.$this->extensionFile");
    }
}
