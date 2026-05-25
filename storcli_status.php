<?php
/* storcli-dw - StorCLI HBA Dashboard Plugin for Unraid
 * Backend JSON endpoint - polled every 30s by dashboard JS
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

$storcli = '/usr/local/bin/storcli64';
$warn    = 65;   // orange warning threshold (°C)
$crit    = 75;   // red critical threshold (°C)

if (!file_exists($storcli)) {
    echo json_encode(['error' => 'storcli64 not found at ' . $storcli]);
    exit;
}

$result = [
    'temp'       => null,
    'temp_class' => '',
    'temp_label' => '',
    'model'      => null,
    'pd_count'   => null,
    'ctrl_state' => 'Optimal',
    'alert'      => null,
];

// ROC Temperature - matches "ROC temperature(Degree Celsius) 52"
$temp_output = shell_exec("$storcli /c0 show temperature 2>/dev/null");
if ($temp_output && preg_match('/ROC temperature\s*\([^)]+\)\s+(\d+)/i', $temp_output, $m)) {
    $temp = intval($m[1]);
    $result['temp'] = $temp;
    if ($temp >= $crit) {
        $result['temp_class'] = 'red-text';
        $result['temp_label'] = 'HOT';
        $result['alert']      = "ROC temperature critical: {$temp}°C (threshold: {$crit}°C)";
    } elseif ($temp >= $warn) {
        $result['temp_class'] = 'orange-text';
        $result['temp_label'] = 'WARM';
    } else {
        $result['temp_class'] = 'green-text';
        $result['temp_label'] = 'Cool';
    }
}

// Controller info
$ctrl_output = shell_exec("$storcli /c0 show 2>/dev/null");
if ($ctrl_output) {
    if (preg_match('/Product Name\s*=\s*(.+)/i', $ctrl_output, $m)) {
        $result['model'] = trim($m[1]);
    }
    if (preg_match('/Physical Drives\s*=\s*(\d+)/i', $ctrl_output, $m)) {
        $result['pd_count'] = intval($m[1]);
    }
    // Only present on RAID controllers, not IT mode HBAs
    if (preg_match('/Controller Status\s*=\s*(.+)/i', $ctrl_output, $m)) {
        $result['ctrl_state'] = trim($m[1]);
    }
}

// Fallback model from board info
if (empty($result['model'])) {
    $info = shell_exec("$storcli /c0 show all 2>/dev/null");
    if ($info) {
        if (preg_match('/Product Name\s*=\s*(.+)/i', $info, $m)) {
            $result['model'] = trim($m[1]);
        } elseif (preg_match('/Board Assembly\s*=\s*(.+)/i', $info, $m)) {
            $result['model'] = trim($m[1]);
        }
    }
}

echo json_encode(['success' => $result]);
