<?php
// PDF Export for Order Transaction Report
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

// Generate PDF content
$html = generatePDFHTML($groupedData, $summary, $reportType, $periodType, $month, $startDate, $endDate);

// Set headers for PDF download
$filename = 'Laporan_Transaksi_Order_' . date('Y-m-d_H-i-s') . '.html';
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($html));

// Output the HTML content
echo $html;

function generatePDFHTML($groupedData, $summary, $reportType, $periodType, $month, $startDate, $endDate) {
    $title = 'Laporan Transaksi Order';
    if ($reportType === 'customer') $title .= ' - Group by Customer';
    elseif ($reportType === 'sales') $title .= ' - Group by Sales';
    
    $period = '';
    if ($periodType === 'monthly') {
        $period = 'Periode: ' . date('F Y', strtotime($month . '-01'));
    } else {
        $period = 'Periode: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate));
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . $title . '</title>
        <style>
            @media print {
                body { margin: 0; }
                .page-break { page-break-before: always; }
            }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 11px; 
                margin: 15px; 
                line-height: 1.4;
            }
            .header { 
                text-align: center; 
                margin-bottom: 25px; 
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
            }
            .header h1 { 
                margin: 0; 
                font-size: 16px; 
                font-weight: bold;
            }
            .header p { 
                margin: 3px 0; 
                font-size: 10px;
            }
            .summary { 
                margin-bottom: 20px; 
            }
            .summary table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 15px;
            }
            .summary td { 
                padding: 4px 8px; 
                border: 1px solid #333; 
                font-size: 10px;
            }
            .summary .label { 
                font-weight: bold; 
                background-color: #f0f0f0; 
                width: 30%;
            }
            .data-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px; 
                font-size: 9px;
            }
            .data-table th, .data-table td { 
                padding: 4px 6px; 
                border: 1px solid #333; 
                text-align: left; 
            }
            .data-table th { 
                background-color: #f0f0f0; 
                font-weight: bold; 
                font-size: 9px;
            }
            .group-header { 
                background-color: #e0e0e0; 
                padding: 8px; 
                margin: 10px 0 5px 0; 
                font-weight: bold; 
                font-size: 10px;
                border: 1px solid #333;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .no-data { 
                text-align: center; 
                padding: 20px; 
                font-style: italic; 
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . $title . '</h1>
            <p>' . $period . '</p>
            <p>Dicetak pada: ' . date('d/m/Y H:i:s') . '</p>
        </div>
        
        <div class="summary">
            <table>
                <tr>
                    <td class="label">Total Orders:</td>
                    <td>' . number_format($summary['total_orders']) . '</td>
                    <td class="label">Completed Orders:</td>
                    <td>' . number_format($summary['completed_orders']) . '</td>
                </tr>
                <tr>
                    <td class="label">Total Value:</td>
                    <td>Rp ' . number_format($summary['total_value'], 0, ',', '.') . '</td>
                    <td class="label">Completed Value:</td>
                    <td>Rp ' . number_format($summary['completed_value'], 0, ',', '.') . '</td>
                </tr>
            </table>
        </div>
    ';
    
    if (empty($groupedData)) {
        $html .= '<div class="no-data">Tidak ada data yang sesuai dengan filter yang dipilih.</div>';
    } else {
        foreach ($groupedData as $group) {
        if ($reportType !== 'global') {
            $html .= '<div class="group-header">' . htmlspecialchars($group['name']) . ' (' . $group['total_orders'] . ' orders - Rp ' . number_format($group['total_value'], 0, ',', '.') . ')</div>';
        }
        
        $html .= '
        <table class="data-table">
            <thead>
                <tr>
                    <th>No Order</th>
                    <th>Tanggal Order</th>
                    <th>Customer</th>
                    <th>Sales</th>
                    <th>No Faktur</th>
                    <th>Tanggal Faktur</th>
                    <th class="text-right">Total Faktur</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        foreach ($group['orders'] as $order) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($order['noorder']) . '</td>
                    <td>' . date('d/m/Y', strtotime($order['tanggalorder'])) . '</td>
                    <td>' . htmlspecialchars($order['namacustomer']) . '</td>
                    <td>' . htmlspecialchars($order['namasales']) . '</td>
                    <td>' . ($order['nofaktur'] ? htmlspecialchars($order['nofaktur']) : '-') . '</td>
                    <td>' . ($order['tanggalfaktur'] ? date('d/m/Y', strtotime($order['tanggalfaktur'])) : '-') . '</td>
                    <td class="text-right">Rp ' . number_format($order['totalfaktur'], 0, ',', '.') . '</td>
                    <td class="text-center">' . ucfirst($order['status']) . '</td>
                </tr>
            ';
        }
        
        $html .= '
            </tbody>
        </table>
        ';
        }
    }
    
    $html .= '
    </body>
    </html>
    ';
    
    return $html;
}
?>
