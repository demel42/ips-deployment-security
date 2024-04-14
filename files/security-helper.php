<?php

declare(strict_types=1);

require_once IPS_GetScriptFile(GetLocalConfig('GLOBAL_HELPER'));
require_once IPS_GetScriptFile(GetLocalConfig('VARIABLE_HELPER'));

function Security_Cmp($a, $b)
{
    $a_pos = $a['floorPos'];
    $b_pos = $b['floorPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }

    $a_pos = $a['roomPos'];
    $b_pos = $b['roomPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }

    $a_pos = $a['devPos'];
    $b_pos = $b['devPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }

    $a_id = $a['devId'];
    $b_id = $b['devId'];
    return $a_id - $b_id;
}

function Security_CmpWithGrp($a, $b)
{
    $a_pos = $a['grpPos'];
    $b_pos = $b['grpPos'];
    if ($a_pos != $b_pos) {
        return $a_pos - $b_pos;
    }

    return Security_Cmp($a, $b);
}

function Security_CheckObject($devID)
{
    $dev = IPS_GetObject($devID);
    if ($dev['ObjectType'] == OBJECTTYPE_LINK) {
        $lnk = IPS_GetLink($devID);
        $devID = $lnk['TargetID'];
        $dev = IPS_GetObject($devID);
    }

    $state = -1;
    $type = '';
    $varID = false;

    switch ($dev['ObjectType']) {
        case OBJECTTYPE_INSTANCE:
            $inst = IPS_GetInstance($devID);
            if ($inst['ModuleInfo']['ModuleID'] == '{485D0419-BE97-4548-AA9C-C083EB82E61E}') { // Dummy-Modul
                $varID = @IPS_GetObjectIDByName('Zustand', $devID);
                if ($varID == false) {
                    $varID = IPS_GetObjectIDByName('Status', $devID);
                }
                if ($varID == false) {
                    echo 'Variable "Zustand" or "Status" not found for device ' . $devID;
                    return false;
                }
                $state = GetValueInteger($varID);
            } else {
                $type = Util_Gerate2Typ($devID);
                switch ($type) {
                    case 'HM-SEC-SC-2':
                    case 'HM-SEC-SCO':
                        $varID = IPS_GetObjectIDByIdent('STATE', $devID);
                        $b = GetValueBoolean($varID);
                        $state = $b ? 2 /* Offen */ : 0 /* Geschlossen */;
                        break;
                    case 'HmIP-SWDM':
                    case 'HmIP-SCI':
                        $varID = IPS_GetObjectIDByIdent('STATE', $devID);
                        $i = GetValueInteger($varID);
                        $state = $i ? 2 /* Offen */ : 0 /* Geschlossen */;
                        break;
                    case 'HM-SEC-RHS':
                    case 'HmIP-SRH':
                    case 'HmIP-SWDO':
                    case 'HmIP-SWDO-2':
                        $varID = IPS_GetObjectIDByIdent('STATE', $devID);
                        $state = GetValueInteger($varID);
                        break;
                    default:
                        echo 'Instance ' . $devID . ' has unsupported type "' . $type . '"' . PHP_EOL;
                        break;
                }
            }
            break;
        case OBJECTTYPE_VARIABLE:
            $varID = $devID;
            $var = IPS_GetVariable($devID);
            switch ($var['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    $b = GetValueBoolean($devID);
                    $state = $b ? 2 /* Offen */ : 0 /* Geschlossen */;
                    break;
                case VARIABLETYPE_INTEGER:
                    $state = GetValueInteger($devID);
                    break;
            }
            break;
        default:
            break;
    }

    $ret = ['state' => $state, 'type' => $type, 'varId' => $varID];
    return $ret;
}

function Security_CheckGroup($grpName)
{
    $roomVisu = [];
    $real_roomIDs = IPS_GetChildrenIDs(GetLocalConfig('Räume'));
    $floorIDs = IPS_GetChildrenIDs(GetLocalConfig('Drinnen'));
    foreach ($floorIDs as $floorID) {
        $floor = IPS_GetObject($floorID);
        $roomIDs = $floor['ChildrenIDs'];
        foreach ($roomIDs as $roomID) {
            $room = IPS_GetObject($roomID);
            $roomPos = $room['ObjectPosition'];
            if ($room['ObjectType'] == OBJECTTYPE_LINK) {
                $lnk = IPS_GetLink($roomID);
                $roomID = $lnk['TargetID'];
                $room = IPS_GetObject($roomID);
            }
            foreach ($real_roomIDs as $real_roomID) {
                $real_room = IPS_GetObject($real_roomID);
                if ($room['ObjectName'] == $real_room['ObjectName']) {
                    $roomID = $real_roomID;
                    $room = $real_room;
                    break;
                }
            }
            $roomVisu[$roomID] = [
                'floorId'   => $floor['ObjectID'],
                'floorPos'  => $floor['ObjectPosition'],
                'floorName' => $floor['ObjectName'],
                'roomId'    => $roomID,
                'roomPos'   => $roomPos,
                'roomName'  => $room['ObjectName'],
            ];
        }
    }
    $vpos = 1000;
    $floor = IPS_GetObject(GetLocalConfig('Draussen'));
    $roomIDs = $floor['ChildrenIDs'];
    foreach ($roomIDs as $roomID) {
        $room = IPS_GetObject($roomID);
        $roomPos = $room['ObjectPosition'];
        if ($room['ObjectType'] == OBJECTTYPE_LINK) {
            $lnk = IPS_GetLink($roomID);
            $roomID = $lnk['TargetID'];
            $room = IPS_GetObject($roomID);
        }
        foreach ($real_roomIDs as $real_roomID) {
            $real_room = IPS_GetObject($real_roomID);
            if ($room['ObjectName'] == $real_room['ObjectName']) {
                $roomID = $real_roomID;
                $room = $real_room;
                break;
            }
        }
        $roomVisu[$roomID] = [
            'floorId'   => $floor['ObjectID'],
            'floorPos'  => $floor['ObjectPosition'] + $vpos,
            'floorName' => $floor['ObjectName'],
            'roomId'    => $roomID,
            'roomPos'   => $roomPos,
            'roomName'  => $room['ObjectName'],
        ];
    }

    $visuList = [];
    $grpIDs = IPS_GetChildrenIDs(GetLocalConfig('Sicherheit-Gruppen'));
    foreach ($grpIDs as $grpID) {
        $grp = IPS_GetObject($grpID);
        if ($grpName != '' && $grp['ObjectName'] != $grpName) {
            continue;
        }
        $devIDs = $grp['ChildrenIDs'];
        foreach ($devIDs as $devID) {
            $dev = IPS_GetObject($devID);
            if ($dev['ObjectType'] != OBJECTTYPE_LINK) {
                continue;
            }

            $lnk = IPS_GetLink($devID);
            $devID = $lnk['TargetID'];
            $dev = IPS_GetObject($devID);

            $r = Security_CheckObject($devID);
            if ($r == false) {
                continue;
            }
            $state = $r['state'];
            $type = $r['type'];

            if ($state == -1) {
                continue;
            }

            $roomID = $dev['ParentID'];
            if (isset($roomVisu[$roomID])) {
                $visu = $roomVisu[$roomID];
                $floorName = $visu['floorName'];
            } else {
                $floorName = 'unknown';
                $visu[$roomID] = [
                    'floorId'   => 0,
                    'floorPos'  => 0,
                    'floorName' => 'unbekannt',
                    'roomPos'   => $room['ObjectID'],
                    'roomPos'   => $room['ObjectPosition'],
                    'roomName'  => $room['ObjectName'],
                ];
            }

            $visu['grpId'] = $grp['ObjectID'];
            $visu['grpPos'] = $grp['ObjectPosition'];
            $visu['grpName'] = $grp['ObjectName'];
            $visu['devId'] = $dev['ObjectID'];
            $visu['devPos'] = $dev['ObjectPosition'];
            $visu['devName'] = $dev['ObjectName'];
            $visu['type'] = $type;
            $visu['state'] = $state;
            $visuList[] = $visu;
        }
    }
    return $visuList;
}

function Security_Print($visuList)
{
    usort($visuList, 'Security_CmpWithGrp');

    $grpID = '';
    foreach ($visuList as $visu) {
        if ($grpID != $visu['grpId']) {
            $grpID = $visu['grpId'];
            $grpName = $visu['grpName'];
            echo $grpName . PHP_EOL;
        }
        $floorName = $visu['floorName'];
        $roomName = $visu['roomName'];
        $devName = $visu['devName'];
        $state = $visu['state'];
        $type = $visu['type'];
        echo '  ' . $floorName . '\\' . $roomName . '\\' . $devName . ' => ' . $state . PHP_EOL;
    }

    echo PHP_EOL;
}

function Security_AdjustEvents($scriptID)
{
    $script = IPS_GetObject($scriptID);
    $grpID = $script['ParentID'];
    $grp = IPS_GetObject($grpID);

    $grpName = $grp['ObjectName'];
    echo __FUNCTION__ . ': grp=' . $grpName . PHP_EOL;

    $triggerIDs = [];
    $triggerID2eventID = [];
    $chldIDs = $script['ChildrenIDs'];
    foreach ($chldIDs as $chldID) {
        $chld = IPS_GetObject($chldID);
        if ($chld['ObjectType'] != OBJECTTYPE_EVENT) {
            continue;
        }
        $event = IPS_GetEvent($chldID);
        $triggerID = $event['TriggerVariableID'];
        $triggerIDs[] = $triggerID;
        $triggerID2eventID[$triggerID] = $chldID;
    }
    $varIDs = [];
    $devIDs = $grp['ChildrenIDs'];
    foreach ($devIDs as $devID) {
        $dev = IPS_GetObject($devID);
        if ($dev['ObjectType'] != OBJECTTYPE_LINK) {
            continue;
        }

        $lnk = IPS_GetLink($devID);
        $devID = $lnk['TargetID'];
        $dev = IPS_GetObject($devID);

        $r = Security_CheckObject($devID);
        if ($r == false) {
            continue;
        }
        $varID = $r['varId'];

        if ($varID == false) {
            continue;
        }
        $varIDs[] = $varID;
        if (in_array($varID, $triggerIDs)) {
            $eventID = $triggerID2eventID[$varID];
            echo '  preserve eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
            continue;
        }
        $eventID = IPS_CreateEvent(0);
        IPS_SetParent($eventID, $scriptID);
        IPS_SetEventTrigger($eventID, 1, $varID);
        IPS_SetEventAction($eventID, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);
        IPS_SetEventActive($eventID, true);
        echo '  create eventID ' . $eventID . ' for varID ' . $varID . PHP_EOL;
    }
    foreach ($triggerIDs as $triggerID) {
        if (in_array($triggerID, $varIDs)) {
            continue;
        }
        $eventID = $triggerID2eventID[$triggerID];
        echo '  delete eventID ' . $eventID . ' for varID ' . $triggerID . PHP_EOL;
        IPS_DeleteEvent($eventID);
    }
}

function Security_AdjustEvents4AllGroups()
{
    echo __FUNCTION__ . ':' . PHP_EOL;

    $grpIDs = IPS_GetChildrenIDs(GetLocalConfig('Sicherheit-Gruppen'));
    foreach ($grpIDs as $grpID) {
        $grp = IPS_GetObject($grpID);
        if ($grp['ObjectType'] != OBJECTTYPE_CATEGORY) {
            continue;
        }
        $scriptID = IPS_GetObjectIDByName('Status ermitteln', $grpID);
        Security_AdjustEvents($scriptID);
    }
}

function Security_Calculate4Script($scriptID)
{
    $script = IPS_GetObject($scriptID);
    $grpID = $script['ParentID'];
    $grpName = IPS_GetName($grpID);

    $visuList = Security_CheckGroup($grpName);

    $state = 0;
    foreach ($visuList as $visu) {
        if ($visu['state'] > $state) {
            $state = $visu['state'];
        }
    }

    $varID = Variable_Create($grpID, '', 'Zustand', VARIABLETYPE_INTEGER, 'Local.SecurityState', 0, 0);
    SetValueInteger($varID, $state);

    $visuList = Security_CheckGroup('');
    $html = Security_BuildHtmlBox($visuList);
    $grp = IPS_GetObject($grpID);
    $parID = $grp['ParentID'];

    $varID = Variable_Create($parID, '', 'Übersicht', VARIABLETYPE_STRING, '~HTMLBox', 0, 0);
    SetValueString($varID, $html);

    IPS_LogMessage(__FUNCTION__, 'Gruppe "' . $grpName . '"=' . $state);
}

function Security_BuildHtmlBox($visuList)
{
    usort($visuList, 'Security_Cmp');

    $html = '';
    $html .= '<style>' . PHP_EOL;
    $html .= 'body { margin: 1; padding: 0; font-family: "Open Sans", sans-serif; font-size: 20px; }' . PHP_EOL;
    $html .= 'table { border-collapse: collapse; border: 0px solid; margin: 0.5em;}' . PHP_EOL;
    $html .= 'th, td { padding: 1; }' . PHP_EOL;
    $html .= 'thead, tdata { text-align: left; }' . PHP_EOL;
    $html .= '#spalte_priority { width: 30px; }' . PHP_EOL;
    $html .= '#spalte_floor { width: 15p0x; }' . PHP_EOL;
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

    return $html;
}
