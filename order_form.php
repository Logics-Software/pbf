<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Check permission - only operator, admin, sales, and customer can access
require_roles(['operator', 'admin', 'sales', 'customer']);

$user = current_user();
include __DIR__ . '/includes/header.php';

// Get order ID if editing
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $orderId > 0;

// Generate auto order number for new orders
function generateOrderNumber() {
    require_once __DIR__ . '/includes/db.php';
$pdo = get_pdo_connection();

    $year = date('y'); // 2 digit year
    $month = date('m'); // 2 digit month
    $prefix = "ORD{$year}{$month}";
    
    // Get the last order number for this month
    $stmt = $pdo->prepare("SELECT noorder FROM headerorder WHERE noorder LIKE ? ORDER BY noorder DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastOrder = $stmt->fetch();
    
    if ($lastOrder) {
        // Extract counter from last order number
        $lastCounter = (int)substr($lastOrder['noorder'], -5);
        $newCounter = $lastCounter + 1;
    } else {
        $newCounter = 1;
    }
    
    return $prefix . str_pad($newCounter, 5, '0', STR_PAD_LEFT);
}

// Check if coming from cart
$fromCart = isset($_GET['from_cart']) && $_GET['from_cart'] == '1';
$cartItems = [];

if ($fromCart && $user['role'] === 'customer') {
    // Get cart items for the current customer
    require_once __DIR__ . '/includes/db.php';
    $pdo = get_pdo_connection();
    
    // Check if specific items are selected
    $selectedItems = isset($_GET['selected_items']) ? explode(',', $_GET['selected_items']) : [];
    
    if (!empty($selectedItems)) {
        // Get only selected items
        $placeholders = str_repeat('?,', count($selectedItems) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT c.*, m.namabarang, m.hargajual, m.discjual, m.stokakhir, m.satuan, m.kondisiharga
            FROM cart c
            JOIN masterbarang m ON c.kodebarang = m.kodebarang
            WHERE c.customer_code = ? AND m.status = 'aktif' AND c.kodebarang IN ($placeholders)
            ORDER BY c.created_at DESC
        ");
        $params = array_merge([$user['kodecustomer']], $selectedItems);
        $stmt->execute($params);
    } else {
        // Get all cart items (fallback)
        $stmt = $pdo->prepare('
            SELECT c.*, m.namabarang, m.hargajual, m.discjual, m.stokakhir, m.satuan, m.kondisiharga
            FROM cart c
            JOIN masterbarang m ON c.kodebarang = m.kodebarang
            WHERE c.customer_code = ? AND m.status = "aktif"
            ORDER BY c.created_at DESC
        ');
        $stmt->execute([$user['kodecustomer']]);
    }
    
    $cartItems = $stmt->fetchAll();
    
    // Calculate totals for cart items
    foreach ($cartItems as &$item) {
        $discountPrice = $item['hargajual'] - ($item['hargajual'] * $item['discjual'] / 100);
        $item['discount_price'] = $discountPrice;
        $item['subtotal'] = $discountPrice * $item['quantity'];
    }
}

// Initialize order data
$order = [
    'id' => 0,
    'noorder' => $isEdit ? '' : generateOrderNumber(),
    'tanggalorder' => date('Y-m-d'),
    'kodecustomer' => $fromCart ? $user['kodecustomer'] : '',
    'namacustomer' => $fromCart ? $user['namalengkap'] : '',
    'kodesales' => '',
    'namasales' => '',
    'status' => 'idle',
    'nofaktur' => '',
    'tanggalfaktur' => '',
    'namapengirim' => '',
    'totalorder' => 0,
    'details' => []
];

// Role-based initialization for new orders and editing
if ($user['role'] === 'sales') {
    // Sales role: auto-populate sales data, customer input active
    if (!$isEdit) {
        $order['kodesales'] = $user['kodesales'] ?? '';
        $order['namasales'] = $user['namalengkap'] ?? '';
    } else {
        // For editing, ensure sales data is from user if not already set
        if (empty($order['kodesales'])) {
            $order['kodesales'] = $user['kodesales'] ?? '';
            $order['namasales'] = $user['namalengkap'] ?? '';
        }
    }
} elseif ($user['role'] === 'customer') {
    // Customer role: auto-populate customer data and sales from master customer
    if (!$isEdit) {
        $order['kodecustomer'] = $user['kodecustomer'] ?? '';
        $order['namacustomer'] = $user['namalengkap'] ?? '';
        
        // Auto-fill sales data from master customer
        if (!empty($order['kodecustomer'])) {
            require_once __DIR__ . '/includes/db.php';
            $pdo = get_pdo_connection();
            $stmt = $pdo->prepare('SELECT kodesales, namasales FROM mastercustomer WHERE kodecustomer = ? AND status = "aktif" LIMIT 1');
            $stmt->execute([$order['kodecustomer']]);
            $customerSales = $stmt->fetch();
            
            if ($customerSales && !empty($customerSales['kodesales'])) {
                $order['kodesales'] = $customerSales['kodesales'];
                $order['namasales'] = $customerSales['namasales'];
            }
        }
    } else {
        // For editing, ensure customer data is from user if not already set
        if (empty($order['kodecustomer'])) {
            $order['kodecustomer'] = $user['kodecustomer'] ?? '';
            $order['namacustomer'] = $user['namalengkap'] ?? '';
            
            // Auto-fill sales data from master customer
            if (!empty($order['kodecustomer'])) {
                require_once __DIR__ . '/includes/db.php';
                $pdo = get_pdo_connection();
                $stmt = $pdo->prepare('SELECT kodesales, namasales FROM mastercustomer WHERE kodecustomer = ? AND status = "aktif" LIMIT 1');
                $stmt->execute([$order['kodecustomer']]);
                $customerSales = $stmt->fetch();
                
                if ($customerSales && !empty($customerSales['kodesales'])) {
                    $order['kodesales'] = $customerSales['kodesales'];
                    $order['namasales'] = $customerSales['namasales'];
                }
            }
        }
    }
}

// Load order data if editing
if ($isEdit) {
    require_once __DIR__ . '/includes/db.php';
    $pdo = get_pdo_connection();
    
    $stmt = $pdo->prepare('SELECT * FROM headerorder WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Location: order.php');
        exit;
    }
    
    // Filter by customer code if user role is customer
    if ($user['role'] === 'customer' && !empty($user['kodecustomer'])) {
        if ($order['kodecustomer'] !== $user['kodecustomer']) {
            header('Location: order.php');
            exit;
        }
    }
    
    // Filter by sales code if user role is sales
    if ($user['role'] === 'sales' && !empty($user['kodesales'])) {
        if ($order['kodesales'] !== $user['kodesales']) {
            header('Location: order.php');
            exit;
        }
    }
    
    // Check if order can be edited (only idle status)
    if ($order['status'] !== 'idle') {
        header('Location: order.php?msg=not_editable');
        exit;
    }
    
    // Load order details
    $stmt = $pdo->prepare('SELECT * FROM detailorder WHERE noorder = ? ORDER BY nourut ASC');
    $stmt->execute([$order['noorder']]);
    $order['details'] = $stmt->fetchAll();
}

// Handle product parameter for new orders (from "Beli Sekarang" or "Tambah ke Keranjang")
$preloadProduct = null;
$preloadQuantity = 1;
$isBuyNow = false;
$preloadCustomer = null;

if (!$isEdit && isset($_GET['product'])) {
    require_once __DIR__ . '/includes/db.php';
    $pdo = get_pdo_connection();
    
    $productCode = $_GET['product'];
    $preloadQuantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    $isBuyNow = isset($_GET['buy_now']) && $_GET['buy_now'] == '1';
    
    // Get product data
    $stmt = $pdo->prepare('SELECT * FROM masterbarang WHERE kodebarang = ? AND status = "aktif" LIMIT 1');
    $stmt->execute([$productCode]);
    $preloadProduct = $stmt->fetch();
    
    // Handle customer parameter for auto-filling sales data
    if (isset($_GET['customer']) && !empty($_GET['customer'])) {
        $customerCode = $_GET['customer'];
        $stmt = $pdo->prepare('SELECT kodecustomer, namacustomer, kodesales, namasales FROM mastercustomer WHERE kodecustomer = ? AND status = "aktif" LIMIT 1');
        $stmt->execute([$customerCode]);
        $preloadCustomer = $stmt->fetch();
        
        // If customer found and has sales data, auto-populate sales fields
        if ($preloadCustomer && !empty($preloadCustomer['kodesales'])) {
            $order['kodecustomer'] = $preloadCustomer['kodecustomer'];
            $order['namacustomer'] = $preloadCustomer['namacustomer'];
            $order['kodesales'] = $preloadCustomer['kodesales'];
            $order['namasales'] = $preloadCustomer['namasales'];
        }
    }
    
    // Handle auto-fill sales data when coming from cart
    if ($fromCart && $user['role'] === 'customer' && !empty($order['kodecustomer'])) {
        $stmt = $pdo->prepare('SELECT kodesales, namasales FROM mastercustomer WHERE kodecustomer = ? AND status = "aktif" LIMIT 1');
        $stmt->execute([$order['kodecustomer']]);
        $customerSales = $stmt->fetch();
        
        if ($customerSales && !empty($customerSales['kodesales'])) {
            $order['kodesales'] = $customerSales['kodesales'];
            $order['namasales'] = $customerSales['namasales'];
        }
    }
}

// Get customer and sales data for dropdowns
$customers = [];
$sales = [];
try {
    $pdo = get_pdo_connection();
    
    // Get customers
    $stmt = $pdo->query('SELECT kodecustomer, namacustomer FROM mastercustomer WHERE status = "aktif" ORDER BY namacustomer');
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sales
    $stmt = $pdo->query('SELECT kodesales, namasales FROM mastersales WHERE status = "aktif" ORDER BY namasales');
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
    $customers = [];
    $sales = [];
}

// Status options
$statusOptions = [
    'idle' => 'Idle',
    'proses' => 'Proses',
    'faktur' => 'Faktur',
    'kirim' => 'Kirim',
    'terima' => 'Terima',
    'batal' => 'Batal'
];
?>

<div class="flex-grow-1">
	<div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
			<h3><?php echo $isEdit ? 'Edit Order' : 'Tambah Order'; ?></h3>
            <a href="order.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
		</div>
		
        <form id="orderForm" method="POST">
            <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
            
            <!-- Header Order -->
            <div class="card mb-4">
						<div class="card-header">
                    <h5 class="mb-0">Data Order</h5>
						</div>
						<div class="card-body">
							<div class="row g-3">
								<div class="col-md-6">
                            <label for="noorder" class="form-label">No Order <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="noorder" name="noorder" 
                                   value="<?php echo htmlspecialchars($order['noorder']); ?>" 
                                   <?php echo $isEdit ? 'readonly' : 'readonly'; ?>>
								</div>
								<div class="col-md-6">
                            <label for="tanggalorder" class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="tanggalorder" name="tanggalorder" 
                                   value="<?php echo $order['tanggalorder']; ?>" required>
								</div>
								<div class="col-md-6">
                            <label for="kodecustomer" class="form-label">Customer <span class="text-danger">*</span></label>
                            <?php if ($user['role'] === 'customer'): ?>
                                <!-- Customer role: disabled and auto-populated -->
                                <input type="text" class="form-control" id="kodecustomer" name="kodecustomer" 
                                       value="<?php echo htmlspecialchars($order['kodecustomer']); ?>" 
                                       readonly>
                                <input type="hidden" name="namacustomer" value="<?php echo htmlspecialchars($order['namacustomer']); ?>">
                                <small class="text-muted"><?php echo htmlspecialchars($order['namacustomer']); ?></small>
                            <?php else: ?>
                                <!-- Other roles: active input -->
                                <input type="text" class="form-control" id="kodecustomer" name="kodecustomer" 
                                       list="customerList" placeholder="-- Ketik nama customer --" 
                                       value="<?php echo htmlspecialchars($order['kodecustomer']); ?>" required>
                                <datalist id="customerList">
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo htmlspecialchars($customer['kodecustomer']); ?>" 
                                                label="<?php echo htmlspecialchars($customer['namacustomer']); ?>"
                                                data-name="<?php echo htmlspecialchars($customer['namacustomer']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small id="customerNameDisplay" class="text-muted" style="display: none; margin-left: 12px;"></small>
                            <?php endif; ?>
										</div>
                        <div class="col-md-6">
                            <label for="kodesales" class="form-label">Sales</label>
                            <?php if ($user['role'] === 'sales'): ?>
                                <!-- Sales role: disabled and auto-populated -->
                                <input type="text" class="form-control" id="kodesales" name="kodesales" 
                                       value="<?php echo htmlspecialchars($order['kodesales']); ?>" 
                                       readonly>
                                <input type="hidden" name="namasales" value="<?php echo htmlspecialchars($order['namasales']); ?>">
                                <small class="text-muted"><?php echo htmlspecialchars($order['namasales']); ?></small>
                            <?php elseif ($user['role'] === 'customer'): ?>
                                <!-- Customer role: auto-populated from master customer and locked -->
                                <input type="text" class="form-control" id="kodesales" name="kodesales" 
                                       value="<?php echo htmlspecialchars($order['kodesales']); ?>" 
                                       readonly>
                                <input type="hidden" name="namasales" value="<?php echo htmlspecialchars($order['namasales']); ?>">
                                <small class="text-muted">
                                    <?php if (!empty($order['namasales'])): ?>
                                        <?php echo htmlspecialchars($order['namasales']); ?>
                                        <br><i class="fas fa-info-circle"></i> Data sales diisi otomatis berdasarkan master customer
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-triangle text-warning"></i> Belum ada data sales di master customer
                                    <?php endif; ?>
                                </small>
                            <?php else: ?>
                                <!-- Other roles: active input -->
                                <input type="text" class="form-control" id="kodesales" name="kodesales" 
                                       list="salesList" placeholder="-- Ketik nama sales --" 
                                       value="<?php echo htmlspecialchars($order['kodesales']); ?>">
                                <datalist id="salesList">
                                    <?php foreach ($sales as $sale): ?>
                                        <option value="<?php echo htmlspecialchars($sale['kodesales']); ?>" 
                                                label="<?php echo htmlspecialchars($sale['namasales']); ?>"
                                                data-name="<?php echo htmlspecialchars($sale['namasales']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small id="salesNameDisplay" class="text-muted" style="display: none; margin-left: 12px;"></small>
                            <?php endif; ?>
									</div>
                        <?php if ($order['status'] !== 'idle'): ?>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $order['status'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="status" value="idle">
                        <?php endif; ?>
                        <?php if ($order['status'] !== 'idle'): ?>
								<div class="col-md-6">
                            <label for="nofaktur" class="form-label">No Faktur</label>
                            <input type="text" class="form-control" id="nofaktur" name="nofaktur" 
                                   value="<?php echo htmlspecialchars($order['nofaktur']); ?>">
								</div>
                        <?php endif; ?>
                        <?php if ($order['status'] !== 'idle'): ?>
								<div class="col-md-6">
                            <label for="tanggalfaktur" class="form-label">Tanggal Faktur</label>
                            <input type="date" class="form-control" id="tanggalfaktur" name="tanggalfaktur" 
                                   value="<?php echo $order['tanggalfaktur']; ?>">
								</div>
                        <?php endif; ?>
                        <?php if ($order['status'] !== 'idle'): ?>
								<div class="col-md-6">
                            <label for="namapengirim" class="form-label">Nama Pengirim</label>
                            <input type="text" class="form-control" id="namapengirim" name="namapengirim" 
                                   value="<?php echo htmlspecialchars($order['namapengirim']); ?>">
								</div>
                        <?php endif; ?>
								</div>
						</div>
					</div>
					
            <!-- Detail Items -->
            <div class="card mb-4">
						<div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Detail Barang</h5>
                    <button type="button" class="btn btn-primary btn-sm" id="addItem">
                        <i class="fas fa-plus"></i> Tambah Item
                    </button>
						</div>
						<div class="card-body">
							<div id="itemList">
                        <?php if (count($order['details']) > 0): ?>
                            <?php foreach ($order['details'] as $idx => $detail): ?>
                                <div class="border rounded p-3 mb-3 item-row" data-index="<?php echo $idx; ?>">
                                    <div class="row g-3">
                                        <div class="col-md-1">
                                            <label class="form-label">Kode</label>
                                            <input type="text" class="form-control item-kodebarang" 
                                                   name="details[<?php echo $idx; ?>][kodebarang]" 
                                                   value="<?php echo htmlspecialchars($detail['kodebarang']); ?>" readonly>
									</div>
                                        <div class="col-md-3">
                                            <label class="form-label">Nama Barang</label>
                                            <input type="text" class="form-control item-namabarang" 
                                                   name="details[<?php echo $idx; ?>][namabarang]" 
                                                   value="<?php echo htmlspecialchars($detail['namabarang']); ?>" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Satuan</label>
                                            <input type="text" class="form-control item-satuan" 
                                                   name="details[<?php echo $idx; ?>][satuan]" 
                                                   value="<?php echo htmlspecialchars($detail['satuan']); ?>" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Jumlah</label>
                                            <input type="number" class="form-control item-jumlah" 
                                                   name="details[<?php echo $idx; ?>][jumlah]" 
                                                   value="<?php echo $detail['jumlah']; ?>" min="1" 
                                                   onchange="recalculateItemTotal(this.closest('.item-row'))" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Harga Satuan</label>
                                            <input type="text" class="form-control item-hargasatuan" 
                                                   name="details[<?php echo $idx; ?>][hargasatuan]" 
                                                   value="<?php echo number_format($detail['hargasatuan'], 0, ',', '.'); ?>" 
                                                   data-original-value="<?php echo $detail['hargasatuan']; ?>" 
                                                   onchange="updateHargaSatuan(this)" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Discount %</label>
                                            <input type="number" class="form-control item-discount" 
                                                   name="details[<?php echo $idx; ?>][discount]" 
                                                   value="<?php echo $detail['discount']; ?>" min="0" max="100" step="0.01"
                                                   onchange="recalculateItemTotal(this.closest('.item-row'))">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Total Harga</label>
                                            <input type="text" class="form-control item-totalharga" 
                                                   name="details[<?php echo $idx; ?>][totalharga]" 
                                                   value="<?php echo number_format($detail['totalharga'], 0, ',', '.'); ?>" 
                                                   data-original-value="<?php echo $detail['totalharga']; ?>" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-grid">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                                                    <i class="fas fa-trash"></i>
															</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
									<?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                Belum ada item barang. Klik "Tambah Item" untuk menambah.
									</div>
								<?php endif; ?>
								
								<?php if ($isEdit && count($order['details']) > 0): ?>
								<script>
								// Ensure total is calculated after existing items are rendered
								document.addEventListener('DOMContentLoaded', function() {
									setTimeout(function() {
										calculateTotal();
									}, 200);
								});
								</script>
								<?php endif; ?>
							</div>
						</div>
					</div>
					
            <!-- Total Order and Submit Button -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
                            <span><h5>Total Order</h5></span>
                            <span><h5 id="totalOrder">Rp 0</h5></span>
						</div>
					</div>
                </div>
                <div class="col-md-6 nb-4">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update Order' : 'Simpan Order'; ?>
						</button>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>

<!-- Modal for selecting barang -->
<div class="modal fade" id="barangModal" tabindex="-1" aria-labelledby="barangModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="barangModalLabel">Pilih Barang</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
						<div class="mb-3">
                    <input type="text" class="form-control" id="modalSearchBarang" placeholder="Cari barang...">
                </div>
                <div id="modalBarangList">
                    <!-- Barang list will be loaded here -->
                </div>
			</div>
		</div>
	</div>
</div>

<!-- Modal for viewing barang images with slide functionality -->
<div class="modal fade" id="barangImagesModal" tabindex="-1" aria-labelledby="barangImagesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="width: 800px; max-width: 90vw;">
        <div class="modal-content" style="height: 600px; max-height: 90vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="barangImagesModalLabel">Foto Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center d-flex flex-column" style="height: calc(100% - 120px); padding: 1rem;">
                <!-- Photo Navigation -->
                <div id="photoNavigation" class="mb-3" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="prevPhoto" title="Foto Sebelumnya">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                            </svg>
                            Sebelumnya
                        </button>
                        <span id="photoCounter" class="badge bg-primary"></span>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="nextPhoto" title="Foto Selanjutnya">
                            Selanjutnya
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Image Container with Fixed Size -->
                <div class="image-container flex-grow-1 d-flex align-items-center justify-content-center" style="position: relative; overflow: hidden; border-radius: 8px; background: #f8f9fa; min-height: 400px;">
                    <img id="modalImage" src="" alt="Foto Barang" style="max-width: 100%; max-height: 100%; transition: transform 0.3s ease; cursor: grab;" draggable="false">
                </div>
                
                <!-- Zoom Controls -->
                <div class="mt-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomOut" title="Zoom Out">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-zoom-out" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11M13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
                            <path d="M10.344 11.742q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1 6.5 6.5 0 0 1-1.398 1.4z"/>
                            <path fill-rule="evenodd" d="M3 6.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5"/>
                        </svg>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomIn" title="Zoom In">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-zoom-in" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11M13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
                            <path d="M10.344 11.742q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1 6.5 6.5 0 0 1-1.398 1.4z"/>
                            <path fill-rule="evenodd" d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 0-1H6V3.5a.5.5 0 0 1 .5-.5"/>
                        </svg>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="resetZoom" title="Reset Zoom">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fullscreen" viewBox="0 0 16 16">
                            <path d="M1.5 1a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0v-4A1.5 1.5 0 0 1 1.5 0h4a.5.5 0 0 1 0 1zM10 .5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 16 1.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5M.5 10a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 0 14.5v-4a.5.5 0 0 1 .5-.5m15 0a.5.5 0 0 1 .5.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5"/>
                        </svg>						</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fitToScreen" title="Fit to Screen">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fullscreen-exit" viewBox="0 0 16 16">
                            <path d="M5.5 0a.5.5 0 0 1 .5.5v4A1.5 1.5 0 0 1 4.5 6h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5m5 0a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 10 4.5v-4a.5.5 0 0 1 .5-.5M0 10.5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 6 11.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5m10 1a1.5 1.5 0 0 1 1.5-1.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0z"/>
                        </svg>
                        </button>
                    </div>
                    <div class="mt-0">
                        <small class="text-muted">
                            <span id="zoomLevel">100%</span> | 
                            <span id="swipeHint">Geser kiri/kanan untuk navigasi foto</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-control:disabled, .form-select:disabled {
    background-color: #f8f9fa !important;
    cursor: not-allowed !important;
    opacity: 0.6;
}

/* Modal quantity input styling */
.quantity-input {
    text-align: center;
    font-weight: bold;
}

.quantity-input:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Barang thumbnail styling */
.barang-thumbnail {
    transition: transform 0.2s ease;
}

.barang-thumbnail:hover {
    transform: scale(1.05);
}

.barang-thumbnail-placeholder {
    border: 2px dashed #dee2e6;
}

/* Images modal styling */
.barang-image-item {
    margin-bottom: 20px;
    text-align: center;
}

.barang-image-item img {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: transform 0.2s ease;
}

.barang-image-item img:hover {
    transform: scale(1.02);
}

.barang-image-caption {
    margin-top: 10px;
    font-size: 14px;
    color: #6c757d;
}
</style>

<script>
// Global variables
let itemIndex = <?php echo count($order['details']); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Customer and sales data for display
    const customerData = {};
    <?php foreach ($customers as $customer): ?>
    customerData['<?php echo htmlspecialchars($customer['kodecustomer']); ?>'] = '<?php echo htmlspecialchars($customer['namacustomer']); ?>';
    <?php endforeach; ?>
    
    const salesData = {};
    <?php foreach ($sales as $sale): ?>
    salesData['<?php echo htmlspecialchars($sale['kodesales']); ?>'] = '<?php echo htmlspecialchars($sale['namasales']); ?>';
    <?php endforeach; ?>
    
    // Auto-fill cart items if coming from cart
    <?php if ($fromCart && !empty($cartItems)): ?>
    const cartItems = <?php echo json_encode($cartItems); ?>;
    
    // Clear existing items first
    const itemsContainer = document.getElementById('itemsContainer');
    if (itemsContainer) {
        itemsContainer.innerHTML = '';
        itemIndex = 0;
    }
    
    // Add each cart item
    cartItems.forEach(function(item) {
        addItemFromCart(item);
    });
    
    // Calculate total after adding all items
    setTimeout(function() {
        calculateTotal();
    }, 500);
    <?php endif; ?>
    
    // Update customer name display
    function updateCustomerName() {
        const selectedCode = document.getElementById('kodecustomer').value;
        const display = document.getElementById('customerNameDisplay');
        if (display) {
            if (selectedCode && customerData[selectedCode]) {
                display.textContent = customerData[selectedCode];
                display.style.display = 'block';
            } else {
                display.style.display = 'none';
            }
        }
    }
    
    // Update sales name display
    function updateSalesName() {
        const selectedCode = document.getElementById('kodesales').value;
        const display = document.getElementById('salesNameDisplay');
        if (display) {
            if (selectedCode && salesData[selectedCode]) {
                display.textContent = salesData[selectedCode];
                display.style.display = 'block';
            } else {
                display.style.display = 'none';
            }
        }
    }
    
    // Validate customer code
    function validateCustomer() {
        const customerInput = document.getElementById('kodecustomer');
        const customerCode = customerInput.value.trim();
        
        if (customerCode && !customerData[customerCode]) {
            // Invalid customer code
            customerInput.classList.add('is-invalid');
            customerInput.classList.remove('is-valid');
            
            // Show error message
            let errorDiv = document.getElementById('customerError');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'customerError';
                errorDiv.className = 'invalid-feedback';
                customerInput.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = 'Customer tidak ditemukan dalam master customer';
        } else if (customerCode && customerData[customerCode]) {
            // Valid customer code
            customerInput.classList.add('is-valid');
            customerInput.classList.remove('is-invalid');
            
            // Remove error message
            const errorDiv = document.getElementById('customerError');
            if (errorDiv) {
                errorDiv.remove();
            }
        } else {
            // Empty input - remove validation classes
            customerInput.classList.remove('is-valid', 'is-invalid');
            
            // Remove error message
            const errorDiv = document.getElementById('customerError');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
    }
    
    // Validate sales code
    function validateSales() {
        const salesInput = document.getElementById('kodesales');
        const salesCode = salesInput.value.trim();
        
        if (salesCode && !salesData[salesCode]) {
            // Invalid sales code
            salesInput.classList.add('is-invalid');
            salesInput.classList.remove('is-valid');
            
            // Show error message
            let errorDiv = document.getElementById('salesError');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'salesError';
                errorDiv.className = 'invalid-feedback';
                salesInput.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = 'Sales tidak ditemukan dalam master sales';
        } else if (salesCode && salesData[salesCode]) {
            // Valid sales code
            salesInput.classList.add('is-valid');
            salesInput.classList.remove('is-invalid');
            
            // Remove error message
            const errorDiv = document.getElementById('salesError');
            if (errorDiv) {
                errorDiv.remove();
            }
        } else {
            // Empty input - remove validation classes
            salesInput.classList.remove('is-valid', 'is-invalid');
            
            // Remove error message
            const errorDiv = document.getElementById('salesError');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
    }
    
    // Add event listeners (with null checks)
    const customerInput = document.getElementById('kodecustomer');
    const salesInput = document.getElementById('kodesales');
    
    if (customerInput) {
        customerInput.addEventListener('input', updateCustomerName);
        customerInput.addEventListener('change', updateCustomerName);
        customerInput.addEventListener('blur', validateCustomer);
    }
    
    if (salesInput) {
        salesInput.addEventListener('input', updateSalesName);
        salesInput.addEventListener('change', updateSalesName);
        salesInput.addEventListener('blur', validateSales);
    }
    
    // Initialize displays
    updateCustomerName();
    updateSalesName();
    
    // Auto-populate sales name if sales code is already filled (from customer data)
    <?php if ($preloadCustomer && !empty($preloadCustomer['kodesales'])): ?>
    setTimeout(function() {
        const salesInput = document.getElementById('kodesales');
        const salesNameDisplay = document.getElementById('salesNameDisplay');
        if (salesInput && salesNameDisplay) {
            salesInput.value = '<?php echo htmlspecialchars($preloadCustomer['kodesales']); ?>';
            salesNameDisplay.textContent = '<?php echo htmlspecialchars($preloadCustomer['namasales']); ?>';
            salesNameDisplay.style.display = 'block';
            salesInput.classList.add('is-valid');
        }
    }, 100);
    <?php endif; ?>
    
    // Add item button
    document.getElementById('addItem').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('barangModal'));
        modal.show();
    });
    
    // Load barang list when modal is shown
    document.getElementById('barangModal').addEventListener('shown.bs.modal', function() {
        loadModalBarangList();
    });
    
    // Handle focus management when modal is hidden
    document.getElementById('barangModal').addEventListener('hidden.bs.modal', function() {
        // Remove focus from any element that might still have it
        const focusedElement = this.querySelector(':focus');
        if (focusedElement) {
            focusedElement.blur();
        }
    });
    
    // Handle focus management when modal is about to be hidden
    document.getElementById('barangModal').addEventListener('hide.bs.modal', function() {
        // Remove focus from any element that might still have it
        const focusedElement = this.querySelector(':focus');
        if (focusedElement) {
            focusedElement.blur();
        }
    });
    
    // Search barang
    document.getElementById('modalSearchBarang').addEventListener('input', function() {
        loadModalBarangList(this.value);
    });
    
    // Remove item
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            if (confirm('Apakah Anda yakin ingin menghapus item ini?')) {
                e.target.closest('.item-row').remove();
                calculateTotal();
            }
        }
    });
    
    // Note: Event listeners are now handled by onchange attributes in HTML
    
    // Form submission
    document.getElementById('orderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {
            id: formData.get('id') || 0,
            noorder: formData.get('noorder'),
            tanggalorder: formData.get('tanggalorder'),
            kodecustomer: formData.get('kodecustomer'),
            namacustomer: formData.get('namacustomer') || customerData[formData.get('kodecustomer')] || '',
            kodesales: formData.get('kodesales') || null,
            namasales: formData.get('namasales') || salesData[formData.get('kodesales')] || null,
            status: formData.get('status'),
            nofaktur: formData.get('nofaktur') || null,
            tanggalfaktur: formData.get('tanggalfaktur') || null,
            namapengirim: formData.get('namapengirim') || null,
            details: []
        };
        
        // Collect details
        document.querySelectorAll('.item-row').forEach(row => {
            const detail = {
                kodebarang: row.querySelector('.item-kodebarang').value,
                namabarang: row.querySelector('.item-namabarang').value,
                satuan: row.querySelector('.item-satuan').value,
                jumlah: parseInt(row.querySelector('.item-jumlah').value),
                hargasatuan: parseInt(row.querySelector('.item-hargasatuan').getAttribute('data-original-value')),
                discount: parseFloat(row.querySelector('.item-discount').value) || 0
            };
            data.details.push(detail);
        });
        
        // Check for validation errors before submission
        const customerInput = document.getElementById('kodecustomer');
        const salesInput = document.getElementById('kodesales');
        
        if (customerInput && customerInput.classList.contains('is-invalid')) {
            alert('Customer tidak valid. Silakan periksa kembali kode customer.');
            customerInput.focus();
            return;
        }
        
        if (salesInput && salesInput.classList.contains('is-invalid')) {
            alert('Sales tidak valid. Silakan periksa kembali kode sales.');
            salesInput.focus();
            return;
        }
        
        // Validate customer and sales before submission (applies to both new and edit orders)
        if (data.kodecustomer && data.kodecustomer.trim() !== '') {
            if (!customerData[data.kodecustomer]) {
                alert('Customer tidak ditemukan dalam master customer');
                return;
            }
        }
        
        if (data.kodesales && data.kodesales.trim() !== '') {
            if (!salesData[data.kodesales]) {
                alert('Sales tidak ditemukan dalam master sales');
                return;
            }
        }

        // Submit to API
        const method = data.id > 0 ? 'PUT' : 'POST';
        const url = 'api/order/index.php';
        
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message || 'Order berhasil disimpan');
                window.location.href = 'order.php';
                        } else {
                alert('Error: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan order');
        });
});

    // Calculate total on page load (with small delay to ensure DOM is ready)
    setTimeout(function() {
        calculateTotal();
    }, 100);
    
    // Initialize image modal functionality
    initializeImageModal();
    
    // Auto-add preloaded product if available
    <?php if ($preloadProduct): ?>
    setTimeout(function() {
        const preloadProduct = <?php echo json_encode($preloadProduct); ?>;
        const preloadQuantity = <?php echo $preloadQuantity; ?>;
        const isBuyNow = <?php echo $isBuyNow ? 'true' : 'false'; ?>;
        
        // Add the product to the order
        addItem(preloadProduct, preloadQuantity);
        
        // If it's "Beli Sekarang", show a message
        if (isBuyNow) {
            // Show success message
            const alertDiv = document.createElement('div');
            let message = `<i class="fas fa-bolt me-2"></i><strong>Order Sekarang!</strong> Produk "${preloadProduct.namabarang}" telah ditambahkan ke order dengan jumlah ${preloadQuantity}.`;
            
            // Add sales auto-fill message if applicable
            <?php if ($preloadCustomer && !empty($preloadCustomer['kodesales'])): ?>
            message += `<br><i class="fas fa-user-tie me-2"></i><strong>Info:</strong> Data sales telah diisi otomatis berdasarkan master customer (${preloadCustomer['namasales']}).`;
            <?php endif; ?>
            
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = message + `<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            
            // Insert at the top of the form
            const form = document.getElementById('orderForm');
            form.insertBefore(alertDiv, form.firstChild);
            
            // Auto-dismiss after 7 seconds (longer to read the sales info)
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 7000);
        }
    }, 200);
    <?php endif; ?>
});

// Image Modal Functionality (same as masterbarang.php)
function initializeImageModal() {
    const imageModal = document.getElementById('barangImagesModal');
    const modalImage = document.getElementById('modalImage');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const resetZoomBtn = document.getElementById('resetZoom');
    const fitToScreenBtn = document.getElementById('fitToScreen');
    const zoomLevel = document.getElementById('zoomLevel');
    const photoNavigation = document.getElementById('photoNavigation');
    const prevPhotoBtn = document.getElementById('prevPhoto');
    const nextPhotoBtn = document.getElementById('nextPhoto');
    const photoCounter = document.getElementById('photoCounter');
    
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    
    // Initialize global variables if not exists
    if (typeof window.currentPhotos === 'undefined') {
        window.currentPhotos = [];
        window.currentPhotoIndex = 0;
        window.currentZoom = 1;
        window.translateX = 0;
        window.translateY = 0;
    }
    
    // Photo navigation
    if (prevPhotoBtn) {
        prevPhotoBtn.addEventListener('click', function() {
            if (window.currentPhotoIndex > 0) {
                window.currentPhotoIndex--;
                modalImage.src = window.currentPhotos[window.currentPhotoIndex];
                updatePhotoCounter();
                updateNavigationButtons();
                // Reset zoom when changing photos
                window.currentZoom = 1;
                window.translateX = 0;
                window.translateY = 0;
                updateImageTransform();
                updateZoomLevel();
            }
        });
    }
    
    if (nextPhotoBtn) {
        nextPhotoBtn.addEventListener('click', function() {
            if (window.currentPhotoIndex < window.currentPhotos.length - 1) {
                window.currentPhotoIndex++;
                modalImage.src = window.currentPhotos[window.currentPhotoIndex];
                updatePhotoCounter();
                updateNavigationButtons();
                // Reset zoom when changing photos
                window.currentZoom = 1;
                window.translateX = 0;
                window.translateY = 0;
                updateImageTransform();
                updateZoomLevel();
            }
        });
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (imageModal && imageModal.classList.contains('show')) {
            if (e.key === 'ArrowLeft' && window.currentPhotoIndex > 0) {
                prevPhotoBtn.click();
            } else if (e.key === 'ArrowRight' && window.currentPhotoIndex < window.currentPhotos.length - 1) {
                nextPhotoBtn.click();
            }
        }
    });
    
    function updatePhotoCounter() {
        if (photoCounter) {
            photoCounter.textContent = `${window.currentPhotoIndex + 1} / ${window.currentPhotos.length}`;
        }
    }
    
    function updateNavigationButtons() {
        if (prevPhotoBtn) prevPhotoBtn.disabled = window.currentPhotoIndex === 0;
        if (nextPhotoBtn) nextPhotoBtn.disabled = window.currentPhotoIndex === window.currentPhotos.length - 1;
    }
    
    // Zoom controls
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function() {
            window.currentZoom = Math.min(window.currentZoom * 1.2, 5);
            updateImageTransform();
            updateZoomLevel();
        });
    }
    
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function() {
            window.currentZoom = Math.max(window.currentZoom / 1.2, 0.1);
            updateImageTransform();
            updateZoomLevel();
        });
    }
    
    if (resetZoomBtn) {
        resetZoomBtn.addEventListener('click', function() {
            window.currentZoom = 1;
            window.translateX = 0;
            window.translateY = 0;
            updateImageTransform();
            updateZoomLevel();
        });
    }
    
    if (fitToScreenBtn) {
        fitToScreenBtn.addEventListener('click', function() {
            const container = modalImage.parentElement;
            const containerRect = container.getBoundingClientRect();
            const imageRect = modalImage.getBoundingClientRect();
            
            const scaleX = (containerRect.width - 40) / imageRect.width;
            const scaleY = (containerRect.height - 40) / imageRect.height;
            window.currentZoom = Math.min(scaleX, scaleY, 1);
            
            window.translateX = 0;
            window.translateY = 0;
            updateImageTransform();
            updateZoomLevel();
        });
    }
    
    // Mouse drag functionality
    if (modalImage) {
        modalImage.addEventListener('mousedown', function(e) {
            if (window.currentZoom > 1) {
                isDragging = true;
                startX = e.clientX - window.translateX;
                startY = e.clientY - window.translateY;
                modalImage.style.cursor = 'grabbing';
            }
        });
    }
    
    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            window.translateX = e.clientX - startX;
            window.translateY = e.clientY - startY;
            updateImageTransform();
        }
    });
    
    document.addEventListener('mouseup', function() {
        if (isDragging) {
            isDragging = false;
            if (modalImage) {
                modalImage.style.cursor = window.currentZoom > 1 ? 'grab' : 'default';
            }
        }
    });
    
    // Touch support for zoom and swipe navigation
    let touchStartX = 0;
    let touchStartY = 0;
    let touchStartTime = 0;
    let isSwipeGesture = false;
    
    if (modalImage) {
        modalImage.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                const touch = e.touches[0];
                touchStartX = touch.clientX;
                touchStartY = touch.clientY;
                touchStartTime = Date.now();
                
                if (window.currentZoom > 1) {
                    isDragging = true;
                    startX = touch.clientX - window.translateX;
                    startY = touch.clientY - window.translateY;
                    isSwipeGesture = false;
                } else {
                    isSwipeGesture = true;
                    isDragging = false;
                }
            }
        });
    }
    
    document.addEventListener('touchmove', function(e) {
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            const diffX = touchStartX - touch.clientX;
            const diffY = touchStartY - touch.clientY;
            
            if (isDragging && window.currentZoom > 1) {
                // Zoom drag mode
                e.preventDefault();
                window.translateX = touch.clientX - startX;
                window.translateY = touch.clientY - startY;
                updateImageTransform();
            } else if (isSwipeGesture && window.currentZoom === 1 && window.currentPhotos.length > 1) {
                // Swipe navigation mode
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 10) {
                    e.preventDefault();
                    // Visual feedback during swipe
                    const swipeThreshold = 50;
                    if (Math.abs(diffX) > swipeThreshold && modalImage) {
                        modalImage.style.opacity = '0.7';
                    }
                }
            }
        }
    });
    
    document.addEventListener('touchend', function(e) {
        if (isDragging) {
            isDragging = false;
            if (modalImage && window.currentZoom > 1) {
                modalImage.style.cursor = 'grab';
            }
        }
        
        if (isSwipeGesture && window.currentZoom === 1 && window.currentPhotos.length > 1) {
            const touchEndTime = Date.now();
            const touchDuration = touchEndTime - touchStartTime;
            const touch = e.changedTouches[0];
            const diffX = touchStartX - touch.clientX;
            const diffY = touchStartY - touch.clientY;
            
            // Reset opacity
            if (modalImage) modalImage.style.opacity = '1';
            
            // Check if it's a valid swipe gesture
            if (touchDuration < 500 && Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0 && window.currentPhotoIndex < window.currentPhotos.length - 1) {
                    // Swipe left - next photo
                    if (nextPhotoBtn) nextPhotoBtn.click();
                } else if (diffX < 0 && window.currentPhotoIndex > 0) {
                    // Swipe right - previous photo
                    if (prevPhotoBtn) prevPhotoBtn.click();
                }
            }
        }
        
        isSwipeGesture = false;
    });
    
    // Mouse wheel zoom
    if (modalImage) {
        modalImage.addEventListener('wheel', function(e) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.9 : 1.1;
            window.currentZoom = Math.max(0.1, Math.min(5, window.currentZoom * delta));
            updateImageTransform();
            updateZoomLevel();
        });
    }
    
    function updateImageTransform() {
        if (modalImage) {
            modalImage.style.transform = `scale(${window.currentZoom}) translate(${window.translateX}px, ${window.translateY}px)`;
            modalImage.style.cursor = window.currentZoom > 1 ? 'grab' : 'default';
        }
    }
    
    function updateZoomLevel() {
        if (zoomLevel) {
            zoomLevel.textContent = Math.round(window.currentZoom * 100) + '%';
            updateSwipeHint();
        }
    }
    
    function updateSwipeHint() {
        const swipeHint = document.getElementById('swipeHint');
        if (swipeHint) {
            if (window.currentZoom > 1) {
                swipeHint.textContent = 'Klik dan drag untuk memindahkan gambar';
            } else if (window.currentPhotos.length > 1) {
                swipeHint.textContent = 'Geser kiri/kanan untuk navigasi foto';
            } else {
                swipeHint.textContent = 'Zoom untuk melihat detail';
            }
        }
    }
    
    // Make functions globally available
    window.updatePhotoCounter = updatePhotoCounter;
    window.updateNavigationButtons = updateNavigationButtons;
    window.updateImageTransform = updateImageTransform;
    window.updateZoomLevel = updateZoomLevel;
}
    
    // Load barang list for modal
    function loadModalBarangList(search = '') {
        const container = document.getElementById('modalBarangList');
    
    fetch(`api/barang.php?search=${encodeURIComponent(search)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                let html = '';
                data.data.forEach(barang => {
                    html += `
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex align-items-center">
                        <div class="me-3 position-relative">
                            ${barang.foto && barang.foto.length > 0 ? 
                                `<div class="position-relative">
                                    <img src="${barang.foto[0]}" class="barang-thumbnail" alt="${barang.namabarang}" 
                                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; cursor: pointer;"
                                         onclick="showBarangImages('${encodeURIComponent(JSON.stringify(barang.foto))}', '${barang.namabarang}')">
                                    ${barang.foto.length > 1 ? 
                                        `<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size: 0.6em;">
                                            ${barang.foto.length}
                                        </span>` : ''
                                    }
                                </div>` :
                                `<div class="barang-thumbnail-placeholder" 
                                      style="width: 60px; height: 60px; background-color: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 12px; text-align: center;">
                                      No Image
                                 </div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-primary">${barang.namabarang}</div>
                            <div class="text-muted small mb-1">
                                <span class="badge bg-secondary me-1">${barang.kodebarang}</span>
                                <span class="badge bg-info me-1">${barang.satuan}</span>
                                <span class="badge bg-warning me-1">Stok: ${parseInt(barang.stokakhir).toLocaleString('id-ID')}</span>
                                <span class="badge bg-success me-1">Harga: Rp ${parseInt(barang.hargajual).toLocaleString('id-ID')}</span>
                                ${barang.discjual > 0 ? `<span class="badge bg-danger me-1">Disc: ${barang.discjual}%</span>` : ''}
                            </div>
                        </div>
                                <div class="ms-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 80px;">
                                    <input type="number" class="form-control quantity-input" 
                                           value="1" min="1" max="${barang.stokakhir}" 
                                           data-barang-id="${barang.id}"
                                           data-stok="${barang.stokakhir}"
                                           onchange="validateQuantity(this)">
                                    </div>
                                <button type="button" class="btn btn-success btn-sm" 
                                        onclick="selectBarangWithQuantity('${encodeURIComponent(JSON.stringify(barang))}', this)">
                                    Pilih
                                </button>
                    </div>
                                </div>
                            </div>
                        </div>
                        `;
                });
                container.innerHTML = html;
        } else {
                container.innerHTML = '<div class="alert alert-warning">Tidak ada barang ditemukan.</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-danger">Error loading barang data.</div>';
        });
}

// Show barang images modal with slide functionality
function showBarangImages(photosJson, barangName) {
    try {
        const photos = JSON.parse(decodeURIComponent(photosJson));
        const modal = new bootstrap.Modal(document.getElementById('barangImagesModal'));
        const modalImage = document.getElementById('modalImage');
        const modalTitle = document.getElementById('barangImagesModalLabel');
        const photoNavigation = document.getElementById('photoNavigation');
        const prevPhotoBtn = document.getElementById('prevPhoto');
        const nextPhotoBtn = document.getElementById('nextPhoto');
        const photoCounter = document.getElementById('photoCounter');
        
        // Set global variables for slide functionality
        window.currentPhotos = photos;
        window.currentPhotoIndex = 0;
        window.currentZoom = 1;
        window.translateX = 0;
        window.translateY = 0;
        
        // Set modal title
        modalTitle.textContent = `Foto: ${barangName}`;
        
        if (photos && photos.length > 0) {
            // Set first image
            modalImage.src = photos[0];
            
            // Show/hide navigation based on photo count
            if (photos.length > 1) {
                photoNavigation.style.display = 'block';
                updatePhotoCounter();
                updateNavigationButtons();
            } else {
                photoNavigation.style.display = 'none';
            }
            
            // Reset zoom and position
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateImageTransform();
            updateZoomLevel();
        } else {
            modalImage.src = '';
            photoNavigation.style.display = 'none';
        }
        
        modal.show();
    } catch (error) {
        console.error('Error showing barang images:', error);
        alert('Error loading images');
    }
}

// Validate quantity input
function validateQuantity(input) {
    const quantity = parseInt(input.value);
    const maxStok = parseInt(input.getAttribute('data-stok'));
    
    if (quantity < 1) {
        input.value = 1;
    } else if (quantity > maxStok) {
        input.value = maxStok;
        alert(`Jumlah tidak boleh melebihi stok yang tersedia (${maxStok})`);
    }
}

// Select barang with quantity from modal
function selectBarangWithQuantity(barang, buttonElement) {
    // Get quantity from the input field next to the button
    const quantityInput = buttonElement.parentElement.querySelector('.quantity-input');
    const quantity = parseInt(quantityInput.value) || 1;
    
    // Call the original selectBarang function
    selectBarang(barang, quantity);
}

// Select barang from modal
function selectBarang(barang, quantity = 1) {
    // If barang is a string, parse it as JSON
    if (typeof barang === 'string') {
        barang = JSON.parse(decodeURIComponent(barang));
    }
    
    addItem(barang, quantity);
    
    // Remove focus from any focused element in modal
    const modal = document.getElementById('barangModal');
    const focusedElement = modal.querySelector(':focus');
    if (focusedElement) {
        focusedElement.blur();
    }
    
    // Close modal
    const modalInstance = bootstrap.Modal.getInstance(modal);
    if (modalInstance) {
        modalInstance.hide();
        }
        
        // Clear search
        document.getElementById('modalSearchBarang').value = '';
}

// Add item to order
function addItem(barang, quantity = 1) {
    const container = document.getElementById('itemList');
    const emptyMsg = container.querySelector('.text-center');
    
    // Remove empty message
    if (emptyMsg) {
        emptyMsg.remove();
    }
    
    // Calculate initial total
    const subtotal = quantity * barang.hargajual;
    const discountAmount = subtotal * (barang.discjual / 100);
    const total = subtotal - discountAmount;
    
    const itemHtml = `
        <div class="border rounded p-3 mb-3 item-row" data-index="${itemIndex}">
            <div class="row g-3">
                <div class="col-md-1">
                    <label class="form-label">Kode</label>
                    <input type="text" class="form-control item-kodebarang" 
                           name="details[${itemIndex}][kodebarang]" 
                           value="${barang.kodebarang}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nama Barang</label>
                    <input type="text" class="form-control item-namabarang" 
                           name="details[${itemIndex}][namabarang]" 
                           value="${barang.namabarang}" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Satuan</label>
                    <input type="text" class="form-control item-satuan" 
                           name="details[${itemIndex}][satuan]" 
                           value="${barang.satuan}" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Jumlah</label>
                    <input type="number" class="form-control item-jumlah" 
                           name="details[${itemIndex}][jumlah]" 
                           value="${quantity}" min="1" 
                           onchange="recalculateItemTotal(this.closest('.item-row'))" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Harga Satuan</label>
                    <input type="text" class="form-control item-hargasatuan" 
                           name="details[${itemIndex}][hargasatuan]" 
                           value="${parseInt(barang.hargajual).toLocaleString('id-ID')}" 
                           data-original-value="${barang.hargajual}" 
                           onchange="updateHargaSatuan(this)" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Discount %</label>
                    <input type="number" class="form-control item-discount" 
                           name="details[${itemIndex}][discount]" 
                           value="${barang.discjual || 0}" min="0" max="100" step="0.01"
                           onchange="recalculateItemTotal(this.closest('.item-row'))">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Total Harga</label>
                    <input type="text" class="form-control item-totalharga" 
                           name="details[${itemIndex}][totalharga]" 
                           value="${total.toLocaleString('id-ID')}" 
                           data-original-value="${total}" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                            <i class="fas fa-trash"></i>
                </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
    
    // Recalculate total
        calculateTotal();
}

// Add item from cart to order
function addItemFromCart(cartItem) {
    const container = document.getElementById('itemList');
    const emptyMsg = container.querySelector('.text-center');
    
    // Remove empty message
    if (emptyMsg) {
        emptyMsg.remove();
    }
    
    // Calculate initial total
    const subtotal = cartItem.quantity * cartItem.hargajual;
    const discountAmount = subtotal * (cartItem.discjual / 100);
    const total = subtotal - discountAmount;
    
    const itemHtml = `
        <div class="border rounded p-3 mb-3 item-row" data-index="${itemIndex}">
            <div class="row g-3">
                <div class="col-md-1">
                    <label class="form-label">Kode</label>
                    <input type="text" class="form-control item-kodebarang" 
                           name="details[${itemIndex}][kodebarang]"
                           value="${cartItem.kodebarang}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nama Barang</label>
                    <input type="text" class="form-control item-namabarang" 
                           name="details[${itemIndex}][namabarang]"
                           value="${cartItem.namabarang}" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Qty</label>
                    <input type="number" class="form-control item-quantity" 
                           name="details[${itemIndex}][quantity]"
                           value="${cartItem.quantity}" min="1" 
                           onchange="recalculateItemTotal(this.closest('.item-row'))">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Harga</label>
                    <input type="number" class="form-control item-hargajual" 
                           name="details[${itemIndex}][hargajual]"
                           value="${cartItem.hargajual}" 
                           onchange="recalculateItemTotal(this.closest('.item-row'))">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Diskon %</label>
                    <input type="number" class="form-control item-discjual" 
                           name="details[${itemIndex}][discjual]"
                           value="${cartItem.discjual}" step="0.01" 
                           onchange="recalculateItemTotal(this.closest('.item-row'))">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Total Harga</label>
                    <input type="text" class="form-control item-totalharga" 
                           name="details[${itemIndex}][totalharga]"
                           value="${total.toLocaleString('id-ID')}" 
                           data-original-value="${total}" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Satuan</label>
                    <input type="text" class="form-control item-satuan" 
                           name="details[${itemIndex}][satuan]"
                           value="${cartItem.satuan}" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Action</label>
                    <button type="button" class="btn btn-danger btn-sm w-100" 
                            onclick="removeItem(${itemIndex})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
    
    // Recalculate total after adding item
    calculateTotal();
}

// Update harga satuan and recalculate
function updateHargaSatuan(input) {
    // Remove formatting and get numeric value
    const rawValue = input.value.replace(/[^\d]/g, '');
    const numericValue = parseInt(rawValue) || 0;
    
    // Update the data-original-value
    input.setAttribute('data-original-value', numericValue);
    
    // Format the display value
    input.value = numericValue.toLocaleString('id-ID');
    
    // Recalculate the item total
    const row = input.closest('.item-row');
    recalculateItemTotal(row);
}

// Recalculate individual item total
function recalculateItemTotal(row) {
    const jumlah = parseInt(row.querySelector('.item-jumlah').value) || 0;
    const hargasatuan = parseInt(row.querySelector('.item-hargasatuan').getAttribute('data-original-value')) || 0;
    const discount = parseFloat(row.querySelector('.item-discount').value) || 0;
    
    const subtotal = jumlah * hargasatuan;
    const discountAmount = subtotal * (discount / 100);
    const total = Math.round(subtotal - discountAmount);
    
    row.querySelector('.item-totalharga').value = total.toLocaleString('id-ID');
    row.querySelector('.item-totalharga').setAttribute('data-original-value', total);
    
    // Recalculate total order
    calculateTotal();
}

// Calculate total order
function calculateTotal() {
    let total = 0;
    const totalInputs = document.querySelectorAll('.item-totalharga');
    
    totalInputs.forEach(input => {
        const value = parseInt(input.getAttribute('data-original-value')) || 0;
        total += value;
    });
    
    const totalElement = document.getElementById('totalOrder');
    if (totalElement) {
        totalElement.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }
    
    // Debug log for troubleshooting
    console.log('Total calculated:', total, 'from', totalInputs.length, 'items');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
