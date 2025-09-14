<?php
// Excel XLSX Export for Order Transaction Report
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

// Generate Excel content using HTML table format (Excel compatible)
$filename = 'Laporan_Transaksi_Order_' . date('Y-m-d_H-i-s') . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Generate Excel content
echo generateExcelHTML($groupedData, $summary, $reportType, $periodType, $month, $startDate, $endDate);

function generateExcelHTML($groupedData, $summary, $reportType, $periodType, $month, $startDate, $endDate) {
    $title = 'Laporan Transaksi Order';
    if ($reportType === 'customer') $title .= ' - Group by Customer';
    elseif ($reportType === 'sales') $title .= ' - Group by Sales';
    
    $period = '';
    if ($periodType === 'monthly') {
        $period = 'Periode: ' . date('F Y', strtotime($month . '-01'));
    } else {
        $period = 'Periode: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate));
    }
    
    // Generate HTML table that Excel can read
    $html = '<!DOCTYPE html>' . "\n";
    $html .= '<html>' . "\n";
    $html .= '<head>' . "\n";
    $html .= '<meta charset="UTF-8">' . "\n";
    $html .= '<style>' . "\n";
    $html .= 'table { border-collapse: collapse; width: 100%; }' . "\n";
    $html .= 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }' . "\n";
    $html .= 'th { background-color: #CCCCCC; font-weight: bold; }' . "\n";
    $html .= '.title { font-size: 16px; font-weight: bold; }' . "\n";
    $html .= '.summary { background-color: #E6E6E6; font-weight: bold; }' . "\n";
    $html .= '</style>' . "\n";
    $html .= '</head>' . "\n";
    $html .= '<body>' . "\n";
    
    // Title
    $html .= '<div class="title">' . htmlspecialchars($title) . '</div>' . "\n";
    $html .= '<div>' . htmlspecialchars($period) . '</div>' . "\n";
    $html .= '<div>Dicetak pada: ' . date('d/m/Y H:i:s') . '</div>' . "\n";
    $html .= '<br>' . "\n";
    
    // Summary table
    $html .= '<table>' . "\n";
    $html .= '<tr>' . "\n";
    $html .= '<td class="summary">Total Orders</td>' . "\n";
    $html .= '<td>' . number_format($summary['total_orders']) . '</td>' . "\n";
    $html .= '<td class="summary">Completed Orders</td>' . "\n";
    $html .= '<td>' . number_format($summary['completed_orders']) . '</td>' . "\n";
    $html .= '</tr>' . "\n";
    $html .= '<tr>' . "\n";
    $html .= '<td class="summary">Total Value</td>' . "\n";
    $html .= '<td>' . $summary['total_value'] . '</td>' . "\n";
    $html .= '<td class="summary">Completed Value</td>' . "\n";
    $html .= '<td>' . $summary['completed_value'] . '</td>' . "\n";
    $html .= '</tr>' . "\n";
    $html .= '</table>' . "\n";
    $html .= '<br>' . "\n";
    
    // Data table
    $html .= '<table>' . "\n";
    
    // Headers
    $html .= '<tr>' . "\n";
    $html .= '<th>No Order</th>' . "\n";
    $html .= '<th>Tanggal Order</th>' . "\n";
    $html .= '<th>Customer</th>' . "\n";
    $html .= '<th>Sales</th>' . "\n";
    $html .= '<th>No Faktur</th>' . "\n";
    $html .= '<th>Tanggal Faktur</th>' . "\n";
    $html .= '<th>Total Faktur</th>' . "\n";
    $html .= '<th>Status</th>' . "\n";
    if ($reportType !== 'global') {
        $html .= '<th>Group</th>' . "\n";
    }
    $html .= '</tr>' . "\n";
    
    // Data rows
    if (empty($groupedData)) {
        $html .= '<tr>' . "\n";
        $html .= '<td colspan="' . ($reportType !== 'global' ? '9' : '8') . '">Tidak ada data yang sesuai dengan filter yang dipilih</td>' . "\n";
        $html .= '</tr>' . "\n";
    } else {
        foreach ($groupedData as $group) {
            foreach ($group['orders'] as $order) {
                $html .= '<tr>' . "\n";
                $html .= '<td>' . htmlspecialchars($order['noorder']) . '</td>' . "\n";
                $html .= '<td>' . date('d/m/Y', strtotime($order['tanggalorder'])) . '</td>' . "\n";
                $html .= '<td>' . htmlspecialchars($order['namacustomer']) . '</td>' . "\n";
                $html .= '<td>' . htmlspecialchars($order['namasales']) . '</td>' . "\n";
                $html .= '<td>' . htmlspecialchars($order['nofaktur'] ? $order['nofaktur'] : '') . '</td>' . "\n";
                $html .= '<td>' . ($order['tanggalfaktur'] ? date('d/m/Y', strtotime($order['tanggalfaktur'])) : '') . '</td>' . "\n";
                $html .= '<td>' . $order['totalfaktur'] . '</td>' . "\n";
                $html .= '<td>' . ucfirst($order['status']) . '</td>' . "\n";
                
                if ($reportType !== 'global') {
                    $html .= '<td>' . htmlspecialchars($group['name']) . '</td>' . "\n";
                }
                
                $html .= '</tr>' . "\n";
            }
        }
    }
    
    $html .= '</table>' . "\n";
    $html .= '</body>' . "\n";
    $html .= '</html>' . "\n";
    
    return $html;
}
?>
