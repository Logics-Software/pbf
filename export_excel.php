<?php
// Excel Export for Order Transaction Report
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

if (!can_access('reports')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Get filter parameters (same as main page)
$reportType = $_GET['type'] ?? 'global';
$periodType = $_GET['period'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$salesFilter = $_GET['sales'] ?? '';
$customerFilter = $_GET['customer'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Apply role-based auto-filtering
if ($user['role'] === 'sales') {
    // For sales role: force filter to their own sales code, ignore all other sales
    $salesFilter = $user['kodesales']; // Force to user's sales code
} elseif ($user['role'] === 'customer') {
    // For customer role: force filter to their own customer code, ignore all other filters
    $customerFilter = $user['kodecustomer']; // Force to user's customer code
    $salesFilter = ''; // Ignore sales filter completely
}

$pdo = get_pdo_connection();

// Build query (same logic as main page)
$whereConditions = [];
$params = [];

if ($periodType === 'monthly') {
    $whereConditions[] = "DATE_FORMAT(h.tanggalorder, '%Y-%m') = ?";
    $params[] = $month;
} else {
    if ($startDate) {
        $whereConditions[] = "h.tanggalorder >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $whereConditions[] = "h.tanggalorder <= ?";
        $params[] = $endDate;
    }
}

if ($salesFilter) {
    $whereConditions[] = "h.kodesales = ?";
    $params[] = $salesFilter;
}

if ($customerFilter) {
    $whereConditions[] = "h.kodecustomer = ?";
    $params[] = $customerFilter;
}

if ($statusFilter) {
    $whereConditions[] = "h.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$baseQuery = "
    SELECT 
        h.noorder,
        h.tanggalorder,
        h.namacustomer,
        h.namasales,
        h.nofaktur,
        h.tanggalfaktur,
        h.totalorder as totalfaktur,
        h.status
    FROM headerorder h
    $whereClause
    ORDER BY h.tanggalorder DESC, h.noorder DESC
";

$stmt = $pdo->prepare($baseQuery);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Group data
$groupedData = [];
$summary = [
    'total_orders' => count($orders),
    'total_value' => array_sum(array_column($orders, 'totalfaktur')),
    'completed_orders' => 0,
    'completed_value' => 0
];

foreach ($orders as $order) {
    if ($order['status'] === 'terima') {
        $summary['completed_orders']++;
        $summary['completed_value'] += $order['totalfaktur'];
    }
    
    if ($reportType === 'customer') {
        $key = $order['namacustomer'];
    } elseif ($reportType === 'sales') {
        $key = $order['namasales'];
    } else {
        $key = 'all';
    }
    
    if (!isset($groupedData[$key])) {
        $groupedData[$key] = [
            'name' => $key,
            'orders' => [],
            'total_orders' => 0,
            'total_value' => 0
        ];
    }
    
    $groupedData[$key]['orders'][] = $order;
    $groupedData[$key]['total_orders']++;
    $groupedData[$key]['total_value'] += $order['totalfaktur'];
}

// Generate Excel content
$content = generateExcelContent($groupedData, $summary, $reportType, $periodType, $month, $startDate, $endDate);
$filename = 'Laporan_Transaksi_Order_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for Excel download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($content));

// Add BOM for proper UTF-8 encoding in Excel
echo "\xEF\xBB\xBF";

// Output the CSV content
echo $content;

function generateExcelContent($groupedData, $summary, $reportType, $periodType, $month, $startDate, $endDate) {
    $title = 'Laporan Transaksi Order';
    if ($reportType === 'customer') $title .= ' - Group by Customer';
    elseif ($reportType === 'sales') $title .= ' - Group by Sales';
    
    $period = '';
    if ($periodType === 'monthly') {
        $period = 'Periode: ' . date('F Y', strtotime($month . '-01'));
    } else {
        $period = 'Periode: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate));
    }
    
    $content = '';
    
    // Header information
    $content .= $title . "\n";
    $content .= $period . "\n";
    $content .= 'Dicetak pada: ' . date('d/m/Y H:i:s') . "\n";
    $content .= "\n";
    
    // Summary
    $content .= "SUMMARY\n";
    $content .= "Total Orders," . number_format($summary['total_orders']) . "\n";
    $content .= "Completed Orders," . number_format($summary['completed_orders']) . "\n";
    $content .= "Total Value," . $summary['total_value'] . "\n";
    $content .= "Completed Value," . $summary['completed_value'] . "\n";
    $content .= "\n";
    
    // Data headers
    $content .= "No Order,Tanggal Order,Customer,Sales,No Faktur,Tanggal Faktur,Total Faktur,Status";
    if ($reportType !== 'global') {
        $content .= ",Group";
    }
    $content .= "\n";
    
    // Data rows
    if (empty($groupedData)) {
        $content .= '"Tidak ada data yang sesuai dengan filter yang dipilih",,,,,,,';
        if ($reportType !== 'global') {
            $content .= ',';
        }
        $content .= "\n";
    } else {
        foreach ($groupedData as $group) {
            foreach ($group['orders'] as $order) {
                $content .= '"' . str_replace('"', '""', $order['noorder']) . '",';
                $content .= '"' . date('d/m/Y', strtotime($order['tanggalorder'])) . '",';
                $content .= '"' . str_replace('"', '""', $order['namacustomer']) . '",';
                $content .= '"' . str_replace('"', '""', $order['namasales']) . '",';
                $content .= '"' . str_replace('"', '""', $order['nofaktur'] ? $order['nofaktur'] : '') . '",';
                $content .= '"' . ($order['tanggalfaktur'] ? date('d/m/Y', strtotime($order['tanggalfaktur'])) : '') . '",';
                $content .= '"' . $order['totalfaktur'] . '",';
                $content .= '"' . ucfirst($order['status']) . '"';
                
                if ($reportType !== 'global') {
                    $content .= ',"' . str_replace('"', '""', $group['name']) . '"';
                }
                
                $content .= "\n";
            }
        }
    }
    
    return $content;
}
?>
