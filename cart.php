<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
if ($user['role'] !== 'customer') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
$pdo = get_pdo_connection();

// Get cart items
$stmt = $pdo->prepare('
    SELECT c.*, m.namabarang, m.hargajual, m.discjual, m.stokakhir, m.foto, m.satuan, m.kondisiharga
    FROM cart c
    JOIN masterbarang m ON c.kodebarang = m.kodebarang
    WHERE c.customer_code = ? AND m.status = "aktif"
    ORDER BY c.created_at DESC
');
$stmt->execute([$user['kodecustomer']]);
$cartItems = $stmt->fetchAll();


// Calculate totals
$totalItems = 0;
$totalPrice = 0;

foreach ($cartItems as $key => $item) {
    $discountPrice = $item['hargajual'] - ($item['hargajual'] * $item['discjual'] / 100);
    $cartItems[$key]['discount_price'] = $discountPrice;
    $cartItems[$key]['subtotal'] = $discountPrice * $item['quantity'];
    $totalItems += $item['quantity'];
    $totalPrice += $cartItems[$key]['subtotal'];
}

include __DIR__ . '/includes/header.php';
?>

<style>
.cart-container {
    max-width: 1200px;
    margin: 0 auto;
}

.cart-item {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.cart-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.product-image {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.quantity-btn {
    width: 35px;
    height: 35px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.quantity-btn:hover {
    background: #f8f9fa;
    border-color: #007bff;
}

.quantity-input {
    width: 60px;
    text-align: center;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px;
}

.price-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
}

.empty-cart {
    text-align: center;
    padding: 60px 20px;
}

.empty-cart-icon {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.condition-badge {
    font-size: 0.7rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 8px;
}

.condition-badge.promo {
    background: #17a2b8;
    color: white;
}

.condition-badge.sale {
    background: #4caf50;
    color: white;
}

.condition-badge.spesial {
    background: #ffc107;
    color: #212529;
}

.condition-badge.deals {
    background: #28a745;
    color: white;
}

/* Checkbox styling */
.form-check-input {
    width: 1.2rem;
    height: 1.2rem;
    margin-top: 0;
}

.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.form-check-input:indeterminate {
    background-color: #007bff;
    border-color: #007bff;
}

.form-check-label {
    font-size: 0.9rem;
    margin-left: 0.5rem;
}

/* Select all checkbox */
#selectAll {
    margin-bottom: 0;
}

#selectAll + label {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
}

@media (max-width: 768px) {
    .cart-item {
        margin-bottom: 15px;
    }
    
    .product-image {
        width: 80px;
        height: 80px;
    }
    
    .quantity-controls {
        gap: 8px;
    }
    
    .quantity-btn {
        width: 30px;
        height: 30px;
    }
    
    .quantity-input {
        width: 50px;
        padding: 6px;
    }
}
</style>

<div class="flex-grow-1">
    <div class="container cart-container">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-3">
            <!-- Desktop Breadcrumb -->
            <ol class="breadcrumb d-none d-md-flex">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Keranjang Order</li>
            </ol>
            
            <!-- Mobile Back Button -->
            <div class="d-md-none">
                <a href="dashboard.php" class="btn btn-link p-0 text-decoration-none">
                    <i class="fas fa-arrow-left" style="font-size: 1.2rem; color: #495057;"></i>
                </a>
            </div>
        </nav>
        
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-shopping-cart me-2"></i>Keranjang Order
                </h2>
            </div>
        </div>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h4 class="text-muted">Keranjang Order Kosong</h4>
                <p class="text-muted">Belum ada item di keranjang order Anda.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag me-2"></i>Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Select All Checkbox -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" checked>
                            <label class="form-check-label fw-bold" for="selectAll">
                                Pilih Semua Barang
                            </label>
                        </div>
                    </div>
                    
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item">
                            <div class="row align-items-center p-3">
                                <div class="col-md-1 col-2">
                                    <div class="form-check">
                                        <input class="form-check-input item-checkbox" type="checkbox" 
                                               id="item_<?php echo $item['kodebarang']; ?>" 
                                               value="<?php echo $item['kodebarang']; ?>" 
                                               checked
                                               onchange="updateSelectedItems()">
                                    </div>
                                </div>
                                <div class="col-md-2 col-3">
                                    <?php 
                                    $photos = json_decode($item['foto'], true);
                                    $mainImage = (!empty($photos) && !empty($photos[0]) && file_exists($photos[0])) ? $photos[0] : 'assets/img/no-image.svg';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                                         alt="<?php echo htmlspecialchars($item['namabarang']); ?>"
                                         class="product-image">
                                </div>
                                
                                <div class="col-md-3 col-7">
                                    <h6 class="mb-2"><?php echo htmlspecialchars($item['namabarang']); ?></h6>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($item['satuan']); ?></p>
                                    
                                    <?php if ($item['kondisiharga'] && $item['kondisiharga'] !== 'normal'): ?>
                                        <?php 
                                        $kondisi = strtolower($item['kondisiharga']);
                                        $badgeText = '';
                                        
                                        switch($kondisi) {
                                            case 'promo':
                                                $badgeText = 'PROMO';
                                                break;
                                            case 'sale':
                                                $badgeText = 'Flash Sale';
                                                break;
                                            case 'spesial':
                                                $badgeText = 'SPESIAL';
                                                break;
                                            case 'deals':
                                                $badgeText = 'DEALS';
                                                break;
                                        }
                                        ?>
                                        <?php if ($badgeText): ?>
                                            <span class="condition-badge <?php echo $kondisi; ?>"><?php echo $badgeText; ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2 col-6">
                                    <div class="quantity-controls">
                                        <button class="quantity-btn" onclick="updateQuantity('<?php echo $item['kodebarang']; ?>', <?php echo $item['quantity'] - 1; ?>)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" 
                                               class="quantity-input" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" 
                                               max="<?php echo $item['stokakhir']; ?>"
                                               onchange="updateQuantity('<?php echo $item['kodebarang']; ?>', this.value)">
                                        <button class="quantity-btn" onclick="updateQuantity('<?php echo $item['kodebarang']; ?>', <?php echo $item['quantity'] + 1; ?>)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-2 col-6">
                                    <div class="text-end">
                                        <?php if ($item['discjual'] > 0): ?>
                                            <div class="text-success fw-bold">Rp <?php echo number_format($item['discount_price'], 0, ',', '.'); ?></div>
                                            <div class="text-muted small text-decoration-line-through">Rp <?php echo number_format($item['hargajual'], 0, ',', '.'); ?></div>
                                        <?php else: ?>
                                            <div class="text-success fw-bold">Rp <?php echo number_format($item['hargajual'], 0, ',', '.'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-2 col-12">
                                    <div class="text-end">
                                        <div class="fw-bold text-primary">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div>
                                        <button class="btn btn-outline-danger btn-sm mt-2" onclick="removeFromCart('<?php echo $item['kodebarang']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="col-lg-4">
                    <div class="price-section">
                        <h4 class="mb-3">Ringkasan Order</h4>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Item:</span>
                            <span><?php echo $totalItems; ?> item</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Total Harga:</span>
                            <span class="fw-bold">Rp <?php echo number_format($totalPrice, 0, ',', '.'); ?></span>
                        </div>
                        <hr style="border-color: rgba(255,255,255,0.3);">
                        <button class="btn btn-light btn-lg w-100" onclick="proceedToCheckout()">
                            <i class="fas fa-credit-card me-2"></i>Lanjut Buat Order
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Checkbox selection functionality
function updateSelectedItems() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectedItems = [];
    let totalSelectedPrice = 0;
    let totalSelectedItems = 0;
    
    // Update select all checkbox state
    const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
    selectAllCheckbox.checked = checkedCount === checkboxes.length;
    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    
    // Calculate totals for selected items
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedItems.push(checkbox.value);
            
            // Find the cart item row and get its data
            const itemRow = checkbox.closest('.cart-item');
            const quantityInput = itemRow.querySelector('.quantity-input');
            const priceElement = itemRow.querySelector('.fw-bold.text-primary');
            
            if (quantityInput && priceElement) {
                const quantity = parseInt(quantityInput.value);
                const priceText = priceElement.textContent.replace('Rp ', '').replace(/\./g, '');
                const price = parseInt(priceText);
                
                totalSelectedItems += quantity;
                totalSelectedPrice += price;
            }
        }
    });
    
    // Update summary section
    updateSummary(totalSelectedItems, totalSelectedPrice);
}

// Update summary section
function updateSummary(totalItems, totalPrice) {
    const totalItemsElement = document.querySelector('.price-section .d-flex.justify-content-between.mb-2 span:last-child');
    const totalPriceElement = document.querySelector('.price-section .d-flex.justify-content-between.mb-3 span:last-child');
    
    if (totalItemsElement) {
        totalItemsElement.textContent = totalItems + ' item';
    }
    
    if (totalPriceElement) {
        totalPriceElement.textContent = 'Rp ' + totalPrice.toLocaleString('id-ID');
    }
}

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedItems();
});

// Update quantity
function updateQuantity(kodebarang, quantity) {
    if (quantity < 1) {
        removeFromCart(kodebarang);
        return;
    }
    
    fetch('api/cart.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            kodebarang: kodebarang,
            quantity: parseInt(quantity)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Reload to update totals
        } else {
            alert(data.message || 'Gagal mengupdate quantity');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengupdate quantity');
    });
}

// Remove from cart
function removeFromCart(kodebarang) {
    if (!confirm('Apakah Anda yakin ingin menghapus item ini dari keranjang?')) {
        return;
    }
    
    fetch('api/cart.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            kodebarang: kodebarang
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Reload to update cart
        } else {
            alert(data.message || 'Gagal menghapus item dari keranjang');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menghapus item');
    });
}

// Proceed to checkout
function proceedToCheckout() {
    const selectedItems = [];
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Pilih minimal satu barang untuk checkout');
        return;
    }
    
    checkboxes.forEach(checkbox => {
        selectedItems.push(checkbox.value);
    });
    
    // Redirect to order form with selected cart items
    const selectedItemsParam = selectedItems.join(',');
    window.location.href = `order_form.php?from_cart=1&selected_items=${selectedItemsParam}`;
}
</script>