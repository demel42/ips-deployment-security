<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('SECURITY_HELPER'));

$visuList = Security_CheckGroup('');
usort($visuList, 'Security_Cmp');

$html = '';

$html .= '<!DOCTYPE html>' . PHP_EOL;
$html .= '<html>' . PHP_EOL;
$html .= '<head>' . PHP_EOL;
$html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . PHP_EOL;
$html .= '<link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet">' . PHP_EOL;
$html .= '<title>Sicherheit-Übersicht</title>' . PHP_EOL;
$html .= '<style>' . PHP_EOL;
$html .= 'html { height: 100%; color: #ffffff; background-color: #303030; overflow: hidden; }' . PHP_EOL;
$html .= 'body { table-cell; text-align: left; vertical-align: top; height: 100%; }' . PHP_EOL;
$html .= '</style>' . PHP_EOL;
$html .= '</head>' . PHP_EOL;
$html .= '<body>' . PHP_EOL;

$html .= '<style>' . PHP_EOL;
$html .= 'body { margin: 1; padding: 0; font-family: "Open Sans", sans-serif; font-size: 9px; }' . PHP_EOL;
$html .= 'table { border-collapse: collapse; border: 0px solid; margin: 0.5em;}' . PHP_EOL;
$html .= 'th, td { padding: 1; }' . PHP_EOL;
$html .= 'thead, tdata { text-align: left; }' . PHP_EOL;
$html .= '#spalte_priority { width: 30px; }' . PHP_EOL;
$html .= '#spalte_floor { width: 150px; }' . PHP_EOL;
$html .= '#spalte_room { width: 250px; }' . PHP_EOL;
$html .= '#spalte_device { width: 300px; }' . PHP_EOL;
$html .= '#spalte_state { width: 20px; }' . PHP_EOL;
$html .= '</style>' . PHP_EOL;

$html .= '<table>' . PHP_EOL;
$html .= '<colgroup><col id="spalte_priority"></colgroup>' . PHP_EOL;
$html .= '<colgroup><col id="spalte_room"></colgroup>' . PHP_EOL;
$html .= '<colgroup><col id="spalte_device"></colgroup>' . PHP_EOL;
$html .= '<colgroup><col id="spalte_state"></colgroup>' . PHP_EOL;
$html .= '<colgroup></colgroup>' . PHP_EOL;

$lastFloorName = '';
foreach ($visuList as $visu) {
    $floorName = $visu['floorName'];
    $roomName = $visu['roomName'];
    $devName = $visu['devName'];
    $state = $visu['state'];
    switch ($state) {
        case 0:
            $state_str = 'geschlossen';
            $state_col = '#32CD32';
            break;
        case 1:
            $state_str = 'lüften';
            $state_col = '#FFD700';
            break;
        case 2:
            $state_str = 'offen';
            $state_col = '#FF0000';
            break;
    }

    if ($floorName != $lastFloorName) {
        $html .= '<tr>' . PHP_EOL;
        $html .= '<td colspan=2><b>' . $floorName . '</b></td>' . PHP_EOL;
        $html .= '<td></td>' . PHP_EOL;
        $html .= '<td></td>' . PHP_EOL;
        $html .= '<td></td>' . PHP_EOL;
        $html .= '</tr>' . PHP_EOL;

        $lastFloorName = $floorName;
    }

    $html .= '<tr>' . PHP_EOL;
    $html .= '<td>&nbsp;</td>' . PHP_EOL;
    $html .= '<td>' . $roomName . '</td>' . PHP_EOL;
    $html .= '<td>' . $devName . '</td>' . PHP_EOL;
    $html .= '<td style="color:' . $state_col . '">' . $state_str . '</td>' . PHP_EOL;
    $html .= '</tr>' . PHP_EOL;
}
$html .= '</tdata>' . PHP_EOL;
$html .= '</table>' . PHP_EOL;

$html .= '</body>' . PHP_EOL;
$html .= '</html>' . PHP_EOL;

echo $html;
