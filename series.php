<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);
$seriesId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get series info
$series = null;
if ($seriesId > 0) {
    $stmt = $conn->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->bind_param("i", $seriesId);
    $stmt->execute();
    $series = $stmt->get_result()->fetch_assoc();
    
    if (!$series) {
        header('Location: profile.php');
        exit();
    }
}

// Get category info
$category = null;
if ($categoryId > 0) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
}

// Get products for this series and category
$products = [];
if ($seriesId > 0) {
    $sql = "
        SELECT p.*, c.name AS category_name, s.name AS series_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN series s ON p.series_id = s.id
        WHERE p.series_id = ?
    ";
    
    if ($categoryId > 0) {
        $sql .= " AND p.category_id = ?";
        $stmt = $conn->prepare($sql . " ORDER BY p.name ASC");
        $stmt->bind_param("ii", $seriesId, $categoryId);
    } else {
        $stmt = $conn->prepare($sql . " ORDER BY p.name ASC");
        $stmt->bind_param("i", $seriesId);
    }
    
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get product statistics
$stats = [
    'total_products' => count($products),
    'min_price' => 0,
    'max_price' => 0,
    'avg_price' => 0,
    'in_stock' => 0
];

if (!empty($products)) {
    $prices = array_column($products, 'price');
    $stats['min_price'] = min($prices);
    $stats['max_price'] = max($prices);
    $stats['avg_price'] = array_sum($prices) / count($prices);
    $stats['in_stock'] = count(array_filter($products, function($p) { return ($p['stock'] ?? 0) > 0; }));
}

// Messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= $series ? htmlspecialchars($series['name']) . ' - Produits' : 'Série' ?> - EKOLED</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="./assets/profile_style.css">
<style>
/* Page specific styles */
.page-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    min-height: calc(100vh - 200px);
}

.page-header {
    background: linear-gradient(135deg, rgba(0,255,204,0.9), rgba(0,212,170,0.9)), url('uploads/<?= $series ? htmlspecialchars($series['image']) : 'default.jpg' ?>');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    color: white;
    padding: 80px 30px;
    text-align: center;
    margin-bottom: 40px;
    border-radius: 25px;
    box-shadow: 0 15px 35px rgba(0,255,204,0.3);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1;
}

.page-header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: float 15s ease-in-out infinite;
    z-index: 2;
}

@keyframes float {
    0%, 100% { transform: rotate(0deg) translate(-50%, -50%); }
    50% { transform: rotate(180deg) translate(-50%, -50%); }
}

.page-header-content {
    position: relative;
    z-index: 3;
}

.page-header h1 {
    font-size: 3.5em;
    margin: 0 0 20px 0;
    text-shadow: 2px 2px 10px rgba(0,0,0,0.7);
    font-weight: 900;
    letter-spacing: -1px;
}

.page-header p {
    font-size: 1.4em;
    opacity: 0.95;
    margin: 0 0 30px 0;
    text-shadow: 1px 1px 5px rgba(0,0,0,0.5);
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.header-stats {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 20px 25px;
    border-radius: 15px;
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 120px;
}

.stat-number {
    display: block;
    font-size: 2em;
    font-weight: 900;
    color: #fff;
    text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
}

.stat-label {
    display: block;
    font-size: 0.9em;
    opacity: 0.9;
    margin-top: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
    padding: 18px 25px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    font-size: 14px;
    transition: all 0.3s ease;
}

.breadcrumb:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.breadcrumb-item {
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
    padding: 6px 12px;
    border-radius: 8px;
}

.breadcrumb-item:hover {
    color: #00ffcc;
    background: rgba(0,255,204,0.1);
}

.breadcrumb-item.active {
    color: #00ffcc;
    font-weight: 600;
    background: rgba(0,255,204,0.15);
}

.breadcrumb-separator {
    color: #999;
    margin: 0 5px;
    font-weight: bold;
}

/* Filters and Controls */
.controls-section {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.filters {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 0.85em;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-select, .filter-input {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
    min-width: 150px;
}

.filter-select:focus, .filter-input:focus {
    outline: none;
    border-color: #00ffcc;
    box-shadow: 0 0 0 3px rgba(0,255,204,0.1);
}

.view-toggle {
    display: flex;
    background: #f8f9fa;
    border-radius: 10px;
    padding: 4px;
}

.view-btn {
    padding: 10px 15px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s ease;
    color: #666;
}

.view-btn.active {
    background: #00ffcc;
    color: #1a1a1a;
    box-shadow: 0 2px 8px rgba(0,255,204,0.3);
}

.view-btn:hover:not(.active) {
    background: rgba(0,255,204,0.1);
    color: #00ffcc;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 50px;
}

.products-grid.list-view {
    grid-template-columns: 1fr;
    gap: 15px;
}

.product-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    cursor: pointer;
    position: relative;
    border: 2px solid transparent;
}

.product-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0,255,204,0.2);
    border-color: #00ffcc;
}

.product-card.list-view {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 15px;
}

.product-card.list-view:hover {
    transform: translateX(10px);
    scale: 1;
}

.product-image-container {
    position: relative;
    overflow: hidden;
    height: 280px;
}

.product-card.list-view .product-image-container {
    width: 150px;
    height: 120px;
    flex-shrink: 0;
    margin-right: 20px;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.product-card:hover .product-image {
    transform: scale(1.15);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.8) 100%);
    opacity: 0;
    transition: opacity 0.3s;
    display: flex;
    align-items: flex-end;
    padding: 20px;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.quick-actions {
    display: flex;
    gap: 10px;
    width: 100%;
}

.quick-btn {
    flex: 1;
    padding: 8px 12px;
    background: rgba(255,255,255,0.9);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.85em;
}

.quick-btn:hover {
    background: white;
    transform: translateY(-2px);
}

.quick-btn.primary {
    background: #00ffcc;
    color: #1a1a1a;
}

.quick-btn.primary:hover {
    background: #00d4aa;
}

.product-badges {
    position: absolute;
    top: 15px;
    left: 15px;
    right: 15px;
    display: flex;
    justify-content: space-between;
}

.product-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.badge-stock {
    background: rgba(40, 167, 69, 0.9);
    color: white;
}

.badge-out-stock {
    background: rgba(220, 53, 69, 0.9);
    color: white;
}

.badge-price {
    background: rgba(0, 255, 204, 0.9);
    color: #1a1a1a;
}

.product-info {
    padding: 25px;
}

.product-card.list-view .product-info {
    padding: 0;
    flex-grow: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-title {
    font-size: 1.3em;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
    transition: color 0.3s;
    line-height: 1.3;
}

.product-card:hover .product-title {
    color: #00ffcc;
}

.product-description {
    color: #666;
    font-size: 0.9em;
    line-height: 1.5;
    margin-bottom: 15px;
    height: 40px;
    overflow: hidden;
    display: -webkit-box;

    -webkit-box-orient: vertical;
}

.product-card.list-view .product-description {
    height: auto;
 
    max-width: 300px;
    margin: 0;
}

.product-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.product-card.list-view .product-details {
    margin: 0;
    flex-direction: column;
    align-items: flex-end;
}

.product-price {
    font-size: 1.4em;
    font-weight: 800;
    color: #00ffcc;
}

.product-stock {
    font-size: 0.85em;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stock-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
}

.stock-indicator.out {
    background: #dc3545;
}

.add-to-cart-btn {
    width: 100%;
    background: linear-gradient(45deg, #00ffcc, #00d4aa);
    color: #1a1a1a;
    border: none;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.95em;
}

.add-to-cart-btn:hover:not(:disabled) {
    background: linear-gradient(45deg, #00d4aa, #00b894);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,255,204,0.3);
}

.add-to-cart-btn:disabled {
    background: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}

.product-card.list-view .add-to-cart-btn {
    width: auto;
    padding: 10px 20px;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #666;
    background: white;
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    margin: 50px 0;
}

.empty-state i {
    font-size: 5em;
    color: #ddd;
    margin-bottom: 25px;
}

.empty-state h3 {
    font-size: 1.8em;
    margin-bottom: 15px;
    color: #333;
}

.empty-state p {
    font-size: 1.1em;
    margin-bottom: 30px;
    line-height: 1.6;
}

.back-to-category {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(45deg, #00ffcc, #00d4aa);
    color: #1a1a1a;
    text-decoration: none;
    padding: 15px 30px;
    border-radius: 30px;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 1.05em;
}

.back-to-category:hover {
    background: linear-gradient(45deg, #00d4aa, #00b894);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,255,204,0.3);
    color: #1a1a1a;
}

/* Animations */
.fade-in-up {
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.slide-in-left {
    animation: slideInLeft 0.6s ease-out;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.scale-in {
    animation: scaleIn 0.5s ease-out;
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.95);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loading-overlay.active {
    opacity: 1;
    visibility: visible;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 5px solid #f0f0f0;
    border-top: 5px solid #00ffcc;
    border-radius: 50%;
    animation: spin 1.2s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .page-container {
        padding: 15px;
    }
    
    .page-header {
        padding: 50px 20px;
        margin-bottom: 30px;
        background-attachment: scroll;
    }
    
    .page-header h1 {
        font-size: 2.5em;
    }
    
    .page-header p {
        font-size: 1.1em;
    }
    
    .header-stats {
        gap: 20px;
    }
    
    .stat-item {
        padding: 15px 20px;
        min-width: 100px;
    }
    
    .stat-number {
        font-size: 1.5em;
    }
    
    .controls-section {
        padding: 20px;
        flex-direction: column;
        align-items: stretch;
    }
    
    .filters {
        justify-content: center;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .product-card.list-view {
        flex-direction: column;
        text-align: center;
    }
    
    .product-card.list-view .product-image-container {
        width: 100%;
        height: 200px;
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .product-card.list-view .product-info {
        flex-direction: column;
        align-items: center;
    }
    
    .product-card.list-view .add-to-cart-btn {
        width: 100%;
        margin-top: 15px;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 2em;
    }
    
    .page-header p {
        font-size: 1em;
    }
    
    .header-stats {
        flex-direction: column;
        gap: 15px;
    }
    
    .breadcrumb {
        padding: 12px 20px;
        font-size: 13px;
        flex-wrap: wrap;
    }
}

/* Messages */
.message { 
    padding: 15px 20px; 
    margin: 20px auto; 
    border-radius: 10px; 
    max-width: 600px;
    text-align: center;
    font-weight: 500;
}
.message.success { 
    background: linear-gradient(45deg, #d4edda, #c3e6cb); 
    color: #155724; 
    border-left: 4px solid #28a745;
}
.message.error { 
    background: linear-gradient(45deg, #f8d7da, #f1b2b7); 
    color: #721c24; 
    border-left: 4px solid #dc3545;
}
</style>
</head>
<body>

<header>
<div class="logo"><img src="uploads/ekoled2.png" width="100px" height="50px"></div>
<nav>
<a href="profile.php">Accueil</a>
<a href="#" onclick="showProductsDropdown()">Produits</a>
<a href="cart.php">Mon Panier</a>
<a href="#">À propos</a>
</nav>
<div class="header-icons">
<input type="text" class="search-bar" placeholder="Rechercher des produits..." id="productSearch">
<a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
<a href="#" id="userIcon"><i class="fas fa-user"></i></a>
</div>
<div class="user-menu" id="userMenu">
<p><strong>Utilisateur :</strong> <?= htmlspecialchars($user['username']) ?></p>
<p>Email : <?= htmlspecialchars($user['email']) ?></p>
<a href="logout.php">Déconnexion</a>
</div>
</header>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Messages -->
<?php if($success): ?><div class="message success fade-in-up"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="message error fade-in-up"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="page-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb slide-in-left">
        <a href="profile.php" class="breadcrumb-item">
            <i class="fas fa-home"></i> Accueil
        </a>
        <span class="breadcrumb-separator">•</span>
        <span class="breadcrumb-item">Produits</span>
        <?php if ($category): ?>
            <span class="breadcrumb-separator">•</span>
            <a href="category.php?id=<?= $categoryId ?>" class="breadcrumb-item">
                <?= htmlspecialchars($category['name']) ?>
            </a>
        <?php endif; ?>
        <?php if ($series): ?>
            <span class="breadcrumb-separator">•</span>
            <span class="breadcrumb-item active"><?= htmlspecialchars($series['name']) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($series): ?>
        <!-- Series Header -->
        <div class="page-header fade-in-up">
            <div class="page-header-content">
                <h1><i class="fas fa-boxes"></i> <?= htmlspecialchars($series['name']) ?></h1>
                <p>
                    <?php if ($category): ?>
                        Découvrez tous les produits de la série <?= htmlspecialchars($series['name']) ?> 
                        dans la catégorie <?= htmlspecialchars($category['name']) ?>
                    <?php else: ?>
                        Découvrez tous les produits de la série <?= htmlspecialchars($series['name']) ?>
                    <?php endif; ?>
                </p>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['total_products'] ?></span>
                        <span class="stat-label">Produit<?= $stats['total_products'] > 1 ? 's' : '' ?></span>
                    </div>
                    <?php if ($stats['total_products'] > 0): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['in_stock'] ?></span>
                        <span class="stat-label">En stock</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($stats['min_price'], 0) ?> DT</span>
                        <span class="stat-label">À partir de</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($stats['avg_price'], 0) ?> DT</span>
                        <span class="stat-label">Prix moyen</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section scale-in">
            <div class="filters">
                <div class="filter-group">
                    <label>Trier par</label>
                    <select class="filter-select" id="sortBy">
                        <option value="name">Nom (A-Z)</option>
                        <option value="name_desc">Nom (Z-A)</option>
                        <option value="price">Prix croissant</option>
                        <option value="price_desc">Prix décroissant</option>
                        <option value="stock">Stock disponible</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Prix max</label>
                    <input type="number" class="filter-input" id="maxPrice" placeholder="Ex: 100" min="0">
                </div>
                <div class="filter-group">
                    <label>Stock</label>
                    <select class="filter-select" id="stockFilter">
                        <option value="">Tous</option>
                        <option value="in_stock">En stock uniquement</option>
                        <option value="out_stock">Rupture de stock</option>
                    </select>
                </div>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" id="gridView" onclick="setView('grid')">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="view-btn" id="listView" onclick="setView('list')">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <!-- Empty State -->
            <div class="empty-state scale-in">
                <i class="fas fa-box-open"></i>
                <h3>Aucun produit disponible</h3>
                <p>Il n'y a pas encore de produits dans cette série.<br>
                   Nous travaillons à enrichir notre catalogue pour vous offrir plus de choix.</p>
                <?php if ($category): ?>
                    <a href="category.php?id=<?= $categoryId ?>" class="back-to-category">
                        <i class="fas fa-arrow-left"></i> Retour à <?= htmlspecialchars($category['name']) ?>
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="back-to-category">
                        <i class="fas fa-arrow-left"></i> Retour à l'accueil
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Products Grid -->
            <div class="products-grid" id="productsContainer">
                <?php foreach ($products as $index => $product): ?>
                    <div class="product-card fade-in-up" style="animation-delay: <?= $index * 0.1 ?>s;" data-product-id="<?= $product['id'] ?>" data-name="<?= strtolower($product['name']) ?>" data-price="<?= $product['price'] ?>" data-stock="<?= $product['stock'] ?? 0 ?>">
                        <div class="product-image-container">
                            <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" onerror="this.src='https://via.placeholder.com/280x220/f0f0f0/999?text=<?= urlencode($product['name']) ?>'">
                            
                            <div class="product-overlay">
                                <div class="quick-actions">
                                    <button class="quick-btn" onclick="viewProduct(<?= $product['id'] ?>)">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <button class="quick-btn primary" onclick="addToCart(<?= $product['id'] ?>)" <?= ($product['stock'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                                        <i class="fas fa-cart-plus"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-badges">
                                <?php if (($product['stock'] ?? 0) > 0): ?>
                                    <span class="product-badge badge-stock">
                                        <i class="fas fa-check-circle"></i> En stock
                                    </span>
                                <?php else: ?>
                                    <span class="product-badge badge-out-stock">
                                        <i class="fas fa-times-circle"></i> Rupture
                                    </span>
                                <?php endif; ?>
                                <span class="product-badge badge-price">
                                    <?= number_format($product['price'], 2) ?> DT
                                </span>
                            </div>
                        </div>
                        
                        <div class="product-info">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-description">
                                Produit de qualité supérieure avec garantie. Parfait pour vos projets d'éclairage.
                            </p>
                            <div class="product-details">
                                <div class="product-price">
                                    <?= number_format($product['price'], 2) ?> DT
                                </div>
                                <div class="product-stock">
                                    <span class="stock-indicator <?= ($product['stock'] ?? 0) <= 0 ? 'out' : '' ?>"></span>
                                    <?php if (($product['stock'] ?? 0) > 0): ?>
                                        <?= $product['stock'] ?> en stock
                                    <?php else: ?>
                                        Rupture de stock
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="add-to-cart-btn" onclick="addToCart(<?= $product['id'] ?>)" <?= ($product['stock'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                                <?php if (($product['stock'] ?? 0) > 0): ?>
                                    <i class="fas fa-cart-plus"></i> Ajouter au panier
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> Non disponible
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Series not found -->
        <div class="empty-state scale-in">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Série introuvable</h3>
            <p>La série que vous recherchez n'existe pas ou a été supprimée.</p>
            <?php if ($category): ?>
                <a href="category.php?id=<?= $categoryId ?>" class="back-to-category">
                    <i class="fas fa-arrow-left"></i> Retour à <?= htmlspecialchars($category['name']) ?>
                </a>
            <?php else: ?>
                <a href="profile.php" class="back-to-category">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="footer-logo">
        <img src="uploads/ekoled2.png" alt="EKOLED Logo">
    </div>
    <div class="footer-categories">
        <?php 
        $allCategories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
        foreach($allCategories as $cat): 
        ?>
            <a href="category.php?id=<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</footer>

<script>
// User menu toggle
document.getElementById('userIcon').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('userMenu').classList.toggle('active');
});

// View toggle functionality
let currentView = 'grid';

function setView(view) {
    currentView = view;
    const container = document.getElementById('productsContainer');
    const gridBtn = document.getElementById('gridView');
    const listBtn = document.getElementById('listView');
    
    if (view === 'list') {
        container.classList.add('list-view');
        container.querySelectorAll('.product-card').forEach(card => {
            card.classList.add('list-view');
        });
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
    } else {
        container.classList.remove('list-view');
        container.querySelectorAll('.product-card').forEach(card => {
            card.classList.remove('list-view');
        });
        listBtn.classList.remove('active');
        gridBtn.classList.add('active');
    }
}

// Add to cart functionality
function addToCart(productId) {
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
    button.disabled = true;
    
    fetch('add_to_list.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + productId
    })
    .then(response => response.text())
    .then(text => {
        showNotification(text, 'success');
        
        // Show success state
        button.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
        button.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.background = '';
            button.disabled = false;
        }, 2000);
    })
    .catch(() => {
        showNotification('Erreur lors de l\'ajout au panier', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// View product details
function viewProduct(productId) {
    showNotification('Fonctionnalité à venir : Vue détaillée du produit', 'info');
    // Future: Redirect to product detail page
    // window.location.href = `product.php?id=${productId}`;
}

// Show products dropdown (redirect to profile page products section)
function showProductsDropdown() {
    showLoading();
    setTimeout(() => {
        window.location.href = 'profile.php#products';
    }, 300);
}

// Show/hide loading overlay
function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

// Hide loading when page loads
window.addEventListener('load', () => {
    hideLoading();
});

// Search functionality
document.getElementById('productSearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    filterProducts();
});

// Filtering and sorting functionality
document.getElementById('sortBy')?.addEventListener('change', filterProducts);
document.getElementById('maxPrice')?.addEventListener('input', filterProducts);
document.getElementById('stockFilter')?.addEventListener('change', filterProducts);

function filterProducts() {
    const searchTerm = document.getElementById('productSearch').value.toLowerCase();
    const sortBy = document.getElementById('sortBy').value;
    const maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
    const stockFilter = document.getElementById('stockFilter').value;
    
    let products = Array.from(document.querySelectorAll('.product-card'));
    
    // Filter products
    products.forEach(card => {
        const name = card.dataset.name;
        const price = parseFloat(card.dataset.price);
        const stock = parseInt(card.dataset.stock);
        
        let visible = true;
        
        // Text search
        if (searchTerm && !name.includes(searchTerm)) {
            visible = false;
        }
        
        // Price filter
        if (price > maxPrice) {
            visible = false;
        }
        
        // Stock filter
        if (stockFilter === 'in_stock' && stock <= 0) {
            visible = false;
        } else if (stockFilter === 'out_stock' && stock > 0) {
            visible = false;
        }
        
        if (visible) {
            card.style.display = 'block';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
            card.style.pointerEvents = 'auto';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Sort visible products
    const visibleProducts = products.filter(card => card.style.display !== 'none');
    
    visibleProducts.sort((a, b) => {
        const aName = a.dataset.name;
        const bName = b.dataset.name;
        const aPrice = parseFloat(a.dataset.price);
        const bPrice = parseFloat(b.dataset.price);
        const aStock = parseInt(a.dataset.stock);
        const bStock = parseInt(b.dataset.stock);
        
        switch (sortBy) {
            case 'name':
                return aName.localeCompare(bName);
            case 'name_desc':
                return bName.localeCompare(aName);
            case 'price':
                return aPrice - bPrice;
            case 'price_desc':
                return bPrice - aPrice;
            case 'stock':
                return bStock - aStock;
            default:
                return 0;
        }
    });
    
    // Reorder DOM elements
    const container = document.getElementById('productsContainer');
    visibleProducts.forEach(product => {
        container.appendChild(product);
    });
    
    // Show/hide empty state
    const hasVisibleProducts = visibleProducts.length > 0;
    if (!hasVisibleProducts && !document.querySelector('.empty-state')) {
        showTemporaryEmptyState();
    } else if (hasVisibleProducts) {
        removeTemporaryEmptyState();
    }
}

function showTemporaryEmptyState() {
    const container = document.getElementById('productsContainer');
    if (!container.querySelector('.temp-empty')) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'temp-empty';
        emptyDiv.style.cssText = `
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        `;
        emptyDiv.innerHTML = `
            <i class="fas fa-search" style="font-size: 3em; color: #ddd; margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">Aucun produit trouvé</h3>
            <p>Essayez de modifier vos critères de recherche.</p>
        `;
        container.appendChild(emptyDiv);
    }
}

function removeTemporaryEmptyState() {
    const tempEmpty = document.querySelector('.temp-empty');
    if (tempEmpty) {
        tempEmpty.remove();
    }
}

// Enhanced product card interactions
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.filter = 'brightness(1.02)';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.filter = 'brightness(1)';
    });
    
    // Add ripple effect on click
    card.addEventListener('click', function(e) {
        // Only add ripple if not clicking on buttons
        if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(0, 255, 204, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
                z-index: 10;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        }
    });
});

// Add ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Notification system
function showNotification(message, type = 'success') {
    // Remove existing notifications
    document.querySelectorAll('.notification').forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${type === 'success' ? 'linear-gradient(45deg, #28a745, #20c997)' : 
                     type === 'error' ? 'linear-gradient(45deg, #dc3545, #e74c3c)' : 
                     'linear-gradient(45deg, #17a2b8, #007bff)'};
        color: white;
        border-radius: 10px;
        z-index: 10000;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        animation: slideInRight 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 350px;
    `;
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'error' ? 'exclamation-circle' : 'info-circle';
    
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Add CSS for notifications
const notificationStyle = document.createElement('style');
notificationStyle.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(notificationStyle);

// Intersection Observer for smooth animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -30px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0) scale(1)';
        }
    });
}, observerOptions);

// Observe animated elements
document.addEventListener('DOMContentLoaded', () => {
    const animatedElements = document.querySelectorAll('.fade-in-up, .slide-in-left, .scale-in');
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px) scale(0.95)';
        observer.observe(el);
    });
    
    // Add staggered animation to product cards
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Smooth scroll behavior
document.documentElement.style.scrollBehavior = 'smooth';

// Page transition effects
document.addEventListener('DOMContentLoaded', function() {
    // Fade in page content
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease';
    
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);
});

// Page transition on navigation
window.addEventListener('beforeunload', function() {
    document.body.style.opacity = '0';
});

// Enhanced keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close any open menus or return to previous page
        const userMenu = document.getElementById('userMenu');
        if (userMenu.classList.contains('active')) {
            userMenu.classList.remove('active');
        }
    }
    
    // Quick filter shortcuts
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'f':
                e.preventDefault();
                document.getElementById('productSearch').focus();
                break;
            case '1':
                e.preventDefault();
                setView('grid');
                break;
            case '2':
                e.preventDefault();
                setView('list');
                break;
        }
    }
});

// Add subtle parallax effect to header
window.addEventListener('scroll', function() {
    const header = document.querySelector('.page-header');
    if (header) {
        const scrolled = window.pageYOffset;
        const parallax = scrolled * 0.3;
        header.style.transform = `translateY(${parallax}px)`;
    }
});

// Performance optimization: Lazy load images
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('.series-image');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                // Remove the opacity animation that was causing the issue
                img.style.opacity = '1';
                img.style.transition = 'transform 0.3s ease';
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => {
        // Set initial opacity to 1 instead of 0
        img.style.opacity = '1';
        imageObserver.observe(img);
    });
});

// Auto-save filter preferences (optional)
function saveFilterPreferences() {
    const preferences = {
        sortBy: document.getElementById('sortBy').value,
        maxPrice: document.getElementById('maxPrice').value,
        stockFilter: document.getElementById('stockFilter').value,
        view: currentView
    };
    localStorage.setItem('productFilters', JSON.stringify(preferences));
}

function loadFilterPreferences() {
    try {
        const saved = localStorage.getItem('productFilters');
        if (saved) {
            const preferences = JSON.parse(saved);
            document.getElementById('sortBy').value = preferences.sortBy || 'name';
            document.getElementById('maxPrice').value = preferences.maxPrice || '';
            document.getElementById('stockFilter').value = preferences.stockFilter || '';
            if (preferences.view) {
                setView(preferences.view);
            }
        }
    } catch (error) {
        console.log('Could not load filter preferences');
    }
}

// Save preferences when filters change
document.getElementById('sortBy')?.addEventListener('change', saveFilterPreferences);
document.getElementById('maxPrice')?.addEventListener('input', saveFilterPreferences);
document.getElementById('stockFilter')?.addEventListener('change', saveFilterPreferences);

// Load preferences on page load
//loadFilterPreferences();

// Log page view for analytics (optional)
console.log('Series page viewed:', '<?= $series ? htmlspecialchars($series['name']) : 'Unknown' ?>');
</script>
</body>
</html>