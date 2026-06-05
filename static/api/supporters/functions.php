<?php
/**
 * Shared functions for the OpenCloudTouch Supporters API.
 * Used by webhook.php, test-webhook.php, and other endpoints.
 */

mb_internal_encoding('UTF-8');

function convertToEur($amount, $currency, $date) {
    if ($currency === 'EUR') {
        return (float)$amount;
    }

    // Historical USD to EUR rates (ECB)
    $usdToEur = [
        '2026-06-02' => 0.8588,
        '2026-06-01' => 0.8588,
        '2026-05-28' => 0.8560,
        '2026-05-10' => 0.8500,
    ];

    if ($currency === 'USD') {
        $rate = $usdToEur[$date] ?? 0.85;
        return round($amount * $rate, 2);
    }

    // Unknown currency - store as-is, log warning
    return (float)$amount;
}

function readSupporters($csv_file) {
    $supporters = [];
    if (!file_exists($csv_file)) {
        return $supporters;
    }

    $csv_content = file_get_contents($csv_file);

    // Remove UTF-8 BOM if present
    $bom = pack('H*', 'EFBBBF');
    if (substr($csv_content, 0, 3) === $bom) {
        $csv_content = substr($csv_content, 3);
    }

    $lines = array_filter(array_map('trim', explode("\n", $csv_content)));
    array_shift($lines); // Remove header

    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) >= 5) {
            $supporters[$row[0]] = [
                'type' => $row[1],
                'amount' => (float)$row[2],
                'monthly' => (float)$row[3],
                'date' => $row[4]
            ];
        }
    }

    return $supporters;
}

function writeSupporters($supporters, $csv_file) {
    // Remove supporters with zero contributions (refunded everything)
    $supporters = array_filter($supporters, function($s) {
        return round($s['amount'], 2) > 0 || round($s['monthly'], 2) > 0;
    });

    // Sort by total DESC, then date ASC
    uasort($supporters, function($a, $b) {
        $totalA = $a['amount'] + $a['monthly'];
        $totalB = $b['amount'] + $b['monthly'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return $a['date'] <=> $b['date'];
    });

    $fp = fopen($csv_file, 'w');
    // Write UTF-8 BOM so readers (Excel, PHP, browsers) correctly interpret encoding
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['name', 'type', 'amount', 'monthlyAmount', 'firstSupportDate']);

    foreach ($supporters as $n => $s) {
        fputcsv($fp, [
            $n,
            $s['type'],
            round($s['amount'], 2),
            round($s['monthly'], 2),
            $s['date']
        ]);
    }

    fclose($fp);
}
