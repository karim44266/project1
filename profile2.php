<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Get user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) { echo "Utilisateur introuvable."; exit(); }

// Messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $type = $_POST['action'];
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? null;
    $stock = $_POST['stock'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $series_id = $_POST['series_id'] ?? null;
    $image = null;

    if (!empty($_FILES['image']['name'])) {
        $image = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
    }

    try {
        if ($type === 'category') {
            if ($id) {
                if ($image) {
                    $stmt = $conn->prepare("UPDATE categories SET name=?, image=? WHERE id=?");
                    $stmt->bind_param("ssi", $name, $image, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
                    $stmt->bind_param("si", $name, $id);
                }
                $stmt->execute();
                $_SESSION['success'] = "Catégorie modifiée avec succès!";
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $image);
                $stmt->execute();
                $_SESSION['success'] = "Catégorie ajoutée!";
            }
        } elseif ($type === 'series') {
            if ($id) {
                if ($image) {
                    $stmt = $conn->prepare("UPDATE series SET name=?, image=? WHERE id=?");
                    $stmt->bind_param("ssi", $name, $image, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE series SET name=? WHERE id=?");
                    $stmt->bind_param("si", $name, $id);
                }
                $stmt->execute();
                $_SESSION['success'] = "Série modifiée avec succès!";
            } else {
                $stmt = $conn->prepare("INSERT INTO series (name, image) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $image);
                $stmt->execute();
                $_SESSION['success'] = "Série ajoutée!";
            }
        } elseif ($type === 'product') {
            $category_id = intval($category_id);
            $series_id = !empty($series_id) ? intval($series_id) : null;
            $stock = intval($stock);

            if ($id) {
                if ($image) {
                    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category_id=?, series_id=?, image=? WHERE id=?");
                    $stmt->bind_param("sdisisi", $name, $price, $stock, $category_id, $series_id, $image, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category_id=?, series_id=? WHERE id=?");
                    $stmt->bind_param("sdiisi", $name, $price, $stock, $category_id, $series_id, $id);
                }
                $stmt->execute();
                $_SESSION['success'] = "Produit modifié avec succès!";
            } else {
                $stmt = $conn->prepare("INSERT INTO products (name, price, stock, category_id, series_id, image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sdiiss", $name, $price, $stock, $category_id, $series_id, $image);
                $stmt->execute();
                $_SESSION['success'] = "Produit ajouté!";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: ".$e->getMessage();
    }

    header("Location: profile.php");
    exit();
}

// Delete handling
if (isset($_GET['delete']) && isset($_GET['type'])) {
    $id = intval($_GET['delete']);
    $type = $_GET['type'];
    $table = $type === 'category' ? 'categories' : ($type === 'series' ? 'series' : 'products');
    if($conn->query("DELETE FROM $table WHERE id = $id")){
        $_SESSION['success'] = ucfirst($type)." supprimé!";
    } else {
        $_SESSION['error'] = "Erreur: ".$conn->error;
    }
    header("Location: profile.php");
    exit();
}

// Get data with statistics
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$series = $conn->query("SELECT * FROM series ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("
    SELECT p.*, c.name AS category_name, s.name AS series_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN series s ON p.series_id = s.id
    ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [
    'total_categories' => count($categories),
    'total_series' => count($series),
    'total_products' => count($products),
    'products_in_stock' => count(array_filter($products, function($p) { return ($p['stock'] ?? 0) > 0; })),
    'total_stock_value' => array_sum(array_map(function($p) { return $p['price'] * ($p['stock'] ?? 0); }, $products))
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - EKOLED</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="./assets/profile_style.css">
<style>
/* Modern Reset & Base */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    line-height: 1.6;
}

/* Modern Header */
.modern-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
}

.header-container {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 2rem;
}

.brand {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 1.5rem;
    font-weight: 800;
    color: #667eea;
    text-decoration: none;
}

.brand img {
    width: 40px;
    height: 40px;
    border-radius: 10px;
}

.nav-links {
    display: flex;
    gap: 2rem;
    list-style: none;
}

.nav-links a {
    color: #4a5568;
    text-decoration: none;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-links a:hover, .nav-links a.active {
    color: #667eea;
    background: rgba(102, 126, 234, 0.1);
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-container {
    position: relative;
}

.search-input {
    background: rgba(247, 250, 252, 0.8);
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 0.75rem 1rem 0.75rem 3rem;
    font-size: 0.9rem;
    width: 300px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
}

.action-btn {
    background: none;
    border: none;
    color: #4a5568;
    cursor: pointer;
    padding: 0.75rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
}

.action-btn:hover {
    color: #667eea;
    background: rgba(102, 126, 234, 0.1);
}

.cart-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: #ff6b6b;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: bold;
}

/* User Dropdown */
.user-dropdown {
    position: relative;
}

.user-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    padding: 1rem;
    min-width: 250px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.user-menu.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-info {
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 1rem;
}

.user-name {
    font-weight: 600;
    color: #2d3748;
}

.user-email {
    color: #718096;
    font-size: 0.9rem;
}

/* Main Content */
.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

/* Messages */
.message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    animation: slideInDown 0.3s ease;
}

.message.success {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
}

.message.error {
    background: linear-gradient(135deg, #f56565, #e53e3e);
    color: white;
}

@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.8));
    border-radius: 24px;
    padding: 3rem;
    margin-bottom: 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(20px);
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(102,126,234,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)" /></svg>');
    z-index: -1;
}

.hero-title {
    font-size: 3rem;
    font-weight: 900;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-text-fill-color: transparent;
    margin-bottom: 1rem;
}

.hero-subtitle {
    font-size: 1.2rem;
    color: #4a5568;
    margin-bottom: 2rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.categories { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-icon.series { background: linear-gradient(135deg, #f093fb, #f5576c); }
.stat-icon.products { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.stat-icon.value { background: linear-gradient(135deg, #43e97b, #38f9d7); }

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #718096;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 3rem;
    backdrop-filter: blur(20px);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.action-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.action-card i {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.action-card h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.action-card p {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Modern Products Panel */
.products-panel {
    position: fixed;
    top: -100%;
    left: 0;
    width: 100%;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: top 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 9999;
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.products-panel.active {
    top: 80px;
}

.panel-inner {
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.panel-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2d3748;
}

.panel-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #718096;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.panel-close:hover {
    color: #e53e3e;
    background: rgba(229, 62, 62, 0.1);
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.category-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    border: 2px solid transparent;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    border-color: #667eea;
}

.category-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.category-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.category-series {
    color: #718096;
    font-size: 0.9rem;
}

/* Modern Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.modal-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2d3748;
}

.close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #718096;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.close:hover {
    color: #e53e3e;
    background: rgba(229, 62, 62, 0.1);
}

/* Modern Tabs */
.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    background: #f7fafc;
    padding: 0.5rem;
    border-radius: 12px;
}

.tab-btn {
    flex: 1;
    padding: 1rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    color: #718096;
}

.tab-btn.active {
    background: white;
    color: #667eea;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Form Styles */
.form-grid {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-weight: 600;
    color: #2d3748;
}

.form-input, .form-select {
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

/* Responsive */
@media (max-width: 768px) {
    .header-container {
        padding: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .nav-links {
        display: none;
    }
    
    .search-input {
        width: 200px;
    }
    
    .main-container {
        padding: 1rem;
    }
    
    .hero-section {
        padding: 2rem 1rem;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-radius: 50%;
    border-top-color: #667eea;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Notification */
.notification {
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: white;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    z-index: 10001;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transform: translateX(400px);
    transition: transform 0.3s ease;
}

.notification.show {
    transform: translateX(0);
}
</style>
</head>
<body>

<!-- Modern Header -->
<header class="modern-header">
    <div class="header-container">
        <a href="profile.php" class="brand">
            <img src="uploads/ekoled2.png" alt="EKOLED">
            <span>EKOLED</span>
        </a>
        
        <nav>
            <ul class="nav-links">
                <li><a href="profile.php" class="active">Dashboard</a></li>
                <li><a href="#" id="productsToggle">Produits</a></li>
                <li><a href="cart.php">Panier</a></li>
                <li><a href="#" id="manageBtn">Gestion</a></li>
            </ul>
        </nav>
        
        <div class="header-actions">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Rechercher des produits...">
            </div>
            
            <button class="action-btn" onclick="window.location.href='cart.php'">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge">3</span>
            </button>
            
            <div class="user-dropdown">
                <button class="action-btn" id="userToggle">
                    <i class="fas fa-user-circle"></i>
                </button>
                <div class="user-menu" id="userMenu">
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Products Panel -->
<div id="productsPanel" class="products-panel" aria-hidden="true">
    <div class="panel-inner">
        <div class="panel-header">
            <h2 class="panel-title">
                <i class="fas fa-boxes"></i> Nos Catégories
            </h2>
            <button class="panel-close" id="productsPanelClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="categories-grid">
            <?php foreach($categories as $cat): ?>
            <a href="category.php?id=<?= $cat['id'] ?>" class="category-card">
                <img src="uploads/<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="category-image">
                <h3 class="category-name"><?= htmlspecialchars($cat['name']) ?></h3>
                <p class="category-series">
                    <?php
                    $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.id) as series_count FROM series s JOIN products p ON s.id = p.series_id WHERE p.category_id = ?");
                    $stmt->bind_param("i", $cat['id']);
                    $stmt->execute();
                    $seriesCount = $stmt->get_result()->fetch_assoc()['series_count'];
                    ?>
                    <?= $seriesCount ?> série<?= $seriesCount > 1 ? 's' : '' ?>
                </p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<!-- Main Content -->
<main class="main-container">
    <!-- Messages -->
    <?php if($success): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <h1 class="hero-title">Tableau de Bord EKOLED</h1>
        <p class="hero-subtitle">
            Gérez votre catalogue de produits d'éclairage LED avec une interface moderne et intuitive
        </p>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon categories">
                    <i class="fas fa-th-large"></i>
                </div>
                <div class="stat-number"><?= $stats['total_categories'] ?></div>
                <div class="stat-label">Catégories</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon series">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-number"><?= $stats['total_series'] ?></div>
                <div class="stat-label">Séries</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon products">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-number"><?= $stats['total_products'] ?></div>
                <div class="stat-label">Produits</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon value">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_stock_value'], 0) ?></div>
                <div class="stat-label">Valeur Stock (DT)</div>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="quick-actions">
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Actions Rapides
        </h2>
        
        <div class="actions-grid">
            <a href="#" class="action-card" onclick="showProductsPanel()">
                <i class="fas fa-eye"></i>
                <h3>Parcourir Produits</h3>
                <p>Explorez notre catalogue par catégories</p>
            </a>
            
            <a href="cart.php" class="action-card">
                <i class="fas fa-shopping-cart"></i>
                <h3>Mon Panier</h3>
                <p>Voir les articles sélectionnés</p>
            </a>
            
            <button class="action-card" onclick="openManageModal()">
                <i class="fas fa-cog"></i>
                <h3>Gérer Stock</h3>
                <p>Ajouter/modifier produits</p>
            </button>
            
            <a href="#recent-products" class="action-card">
                <i class="fas fa-clock"></i>
                <h3>Produits Récents</h3>
                <p>Voir les derniers ajouts</p>
            </a>
        </div>
    </section>

    <!-- Recent Products Preview -->
    <section id="recent-products" class="quick-actions">
        <h2 class="section-title">
            <i class="fas fa-star"></i>
            Produits Récents
        </h2>
        
        <div class="categories-grid">
            <?php 
            $recentProducts = array_slice($products, 0, 6);
            foreach($recentProducts as $prod): 
            ?>
            <div class="category-card">
                <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="category-image">
                <h3 class="category-name"><?= htmlspecialchars($prod['name']) ?></h3>
                <p class="category-series">
                    <?= number_format($prod['price'], 2) ?> DT • 
                    Stock: <?= $prod['stock'] ?? 0 ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>


<!-- Modern Management Modal -->
<div id="manageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fas fa-tools"></i>
                Gestion du Stock
            </h2>
            <button class="close" onclick="closeManageModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="tabs">
            <button class="tab-btn active" data-tab="categories">
                <i class="fas fa-th-large"></i>
                Catégories
            </button>
            <button class="tab-btn" data-tab="series">
                <i class="fas fa-layer-group"></i>
                Séries
            </button>
            <button class="tab-btn" data-tab="products">
                <i class="fas fa-boxes"></i>
                Produits
            </button>
        </div>

        <!-- Categories Tab -->
        <div class="tab-content active" id="categories">
            <h3>Catégories Existantes</h3>
            <div class="categories-grid" style="margin-bottom: 2rem;">
                <?php foreach ($categories as $cat): ?>
                <div class="category-card">
                    <img src="uploads/<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="category-image">
                    <h3 class="category-name"><?= htmlspecialchars($cat['name']) ?></h3>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                        <button class="btn btn-secondary" style="flex: 1; padding: 0.5rem;" onclick="editCategory(<?= $cat['id'] ?>,'<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>','<?= $cat['image'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-secondary" style="flex: 1; padding: 0.5rem; background: #fee; color: #c53030;" onclick="deleteItem(<?= $cat['id'] ?>, 'category')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 id="catFormTitle">Ajouter une Catégorie</h3>
            <form method="POST" enctype="multipart/form-data" id="catForm" class="form-grid">
                <input type="hidden" name="action" value="category">
                <input type="hidden" name="id" id="catId">
                
                <div class="form-group">
                    <label class="form-label">Nom de la catégorie</label>
                    <input type="text" name="name" id="catName" class="form-input" placeholder="Ex: Éclairage LED" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                    <img id="catPreview" src="" style="display:none; width: 100px; height: 100px; object-fit: cover; border-radius: 8px; margin-top: 1rem;">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetCatForm()">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                </div>
            </form>
        </div>

        <!-- Series Tab -->
        <div class="tab-content" id="series">
            <h3>Séries Existantes</h3>
            <div class="categories-grid" style="margin-bottom: 2rem;">
                <?php foreach ($series as $ser): ?>
                <div class="category-card">
                    <img src="uploads/<?= htmlspecialchars($ser['image']) ?>" alt="<?= htmlspecialchars($ser['name']) ?>" class="category-image">
                    <h3 class="category-name"><?= htmlspecialchars($ser['name']) ?></h3>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                        <button class="btn btn-secondary" style="flex: 1; padding: 0.5rem;" onclick="editSeries(<?= $ser['id'] ?>,'<?= htmlspecialchars($ser['name'], ENT_QUOTES) ?>','<?= $ser['image'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-secondary" style="flex: 1; padding: 0.5rem; background: #fee; color: #c53030;" onclick="deleteItem(<?= $ser['id'] ?>, 'series')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 id="serFormTitle">Ajouter une Série</h3>
            <form method="POST" enctype="multipart/form-data" id="serForm" class="form-grid">
                <input type="hidden" name="action" value="series">
                <input type="hidden" name="id" id="serId">
                
                <div class="form-group">
                    <label class="form-label">Nom de la série</label>
                    <input type="text" name="name" id="serName" class="form-input" placeholder="Ex: Série Premium" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                    <img id="serPreview" src="" style="display:none; width: 100px; height: 100px; object-fit: cover; border-radius: 8px; margin-top: 1rem;">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetSerForm()">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Tab -->
        <div class="tab-content" id="products">
            <div class="form-group" style="margin-bottom: 2rem;">
                <input type="text" id="searchInput" class="form-input" placeholder="Rechercher un produit...">
            </div>
            
            <div class="categories-grid" style="margin-bottom: 2rem;" id="productsList">
                <?php foreach ($products as $prod): ?>
                <div class="category-card" data-name="<?= htmlspecialchars(strtolower($prod['name'])) ?>">
                    <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="category-image">
                    <h3 class="category-name"><?= htmlspecialchars($prod['name']) ?></h3>
                    <p class="category-series">
                        <?= number_format($prod['price'], 2) ?> DT<br>
                        Stock: 
                        <input type="number" value="<?= intval($prod['stock'] ?? 0) ?>" 
                               style="width:60px; padding: 0.25rem; border: 1px solid #e2e8f0; border-radius: 4px; text-align: center;" 
                               onchange="updateStock(<?= $prod['id'] ?>, this.value)">
                    </p>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                        <button class="btn btn-secondary" style="flex: 1; padding: 0.5rem;" onclick="editProduct(<?= $prod['id'] ?>,'<?= htmlspecialchars($prod['name'],ENT_QUOTES) ?>',<?= $prod['price'] ?>,<?= intval($prod['stock'] ?? 0) ?>,<?= $prod['category_id'] ?? 0 ?>,<?= $prod['series_id'] ?? 0 ?>,'<?= $prod['image'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-secondary" style="flex: 1; padding: 0.5rem; background: #fee; color: #c53030;" onclick="deleteItem(<?= $prod['id'] ?>, 'product')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 id="prodFormTitle">Ajouter un Produit</h3>
            <form method="POST" enctype="multipart/form-data" id="prodForm" class="form-grid">
                <input type="hidden" name="action" value="product">
                <input type="hidden" name="id" id="prodId">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Nom du produit</label>
                        <input type="text" name="name" id="prodName" class="form-input" placeholder="Ex: LED Strip 5m" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Prix (DT)</label>
                        <input type="number" step="0.01" name="price" id="prodPrice" class="form-input" placeholder="0.00" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" id="prodStock" class="form-input" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Catégorie</label>
                        <select name="category_id" id="prodCat" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Série (Optionnel)</label>
                        <select name="series_id" id="prodSer" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($series as $ser): ?>
                            <option value="<?= $ser['id'] ?>"><?= htmlspecialchars($ser['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                    <img id="prodPreview" src="" style="display:none; width: 100px; height: 100px; object-fit: cover; border-radius: 8px; margin-top: 1rem;">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetProdForm()">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modern JavaScript functionality
class ModernDashboard {
    constructor() {
        this.initEventListeners();
        this.initAnimations();
    }
    
    initEventListeners() {
        // User menu toggle
        document.getElementById('userToggle').addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('userMenu').classList.toggle('active');
        });
        
        // Close user menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-dropdown')) {
                document.getElementById('userMenu').classList.remove('active');
            }
        });
        
        // Products panel
        document.getElementById('productsToggle').addEventListener('click', (e) => {
            e.preventDefault();
            this.showProductsPanel();
        });
        
        document.getElementById('productsPanelClose').addEventListener('click', () => {
            this.hideProductsPanel();
        });
        
        // Close panel on outside click
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('productsPanel');
            if (panel.classList.contains('active') && 
                !e.target.closest('#productsPanel') && 
                !e.target.closest('#productsToggle')) {
                this.hideProductsPanel();
            }
        });
        
        // Escape key to close panels
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideProductsPanel();
                this.closeManageModal();
                document.getElementById('userMenu').classList.remove('active');
            }
        });
        
        // Modal management
        document.getElementById('manageBtn').addEventListener('click', (e) => {
            e.preventDefault();
            this.openManageModal();
        });
        
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchTab(btn));
        });
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            this.filterProducts(e.target.value);
        });
    }
    
    initAnimations() {
        // Animate stats on load
        setTimeout(() => {
            document.querySelectorAll('.stat-number').forEach(stat => {
                this.animateNumber(stat);
            });
        }, 500);
        
        // Stagger animation for cards
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'fadeInUp 0.6s ease forwards';
        });
    }
    
    animateNumber(element) {
        const target = parseInt(element.textContent);
        const duration = 1500;
        const increment = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }
    
    showProductsPanel() {
        document.getElementById('productsPanel').classList.add('active');
        document.getElementById('productsPanel').setAttribute('aria-hidden', 'false');
    }
    
    hideProductsPanel() {
        document.getElementById('productsPanel').classList.remove('active');
        document.getElementById('productsPanel').setAttribute('aria-hidden', 'true');
    }
    
    openManageModal() {
        document.getElementById('manageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    closeManageModal() {
        document.getElementById('manageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    switchTab(btn) {
        // Remove active class from all tabs and content
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Activate clicked tab and corresponding content
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    }
    
    filterProducts(searchTerm) {
        const products = document.querySelectorAll('#productsList .category-card');
        products.forEach(product => {
            const name = product.dataset.name || '';
            const isVisible = name.includes(searchTerm.toLowerCase());
            product.style.display = isVisible ? 'block' : 'none';
        });
    }
    
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Global functions for compatibility
function showProductsPanel() {
    dashboard.showProductsPanel();
}

function openManageModal() {
    dashboard.openManageModal();
}

function closeManageModal() {
    dashboard.closeManageModal();
}

// Form handling functions
function editCategory(id, name, img) {
    document.getElementById('catId').value = id;
    document.getElementById('catName').value = name;
    if (img) {
        document.getElementById('catPreview').src = 'uploads/' + img;
        document.getElementById('catPreview').style.display = 'block';
    }
    document.getElementById('catFormTitle').textContent = 'Modifier la catégorie';
}

function resetCatForm() {
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catPreview').style.display = 'none';
    document.getElementById('catFormTitle').textContent = 'Ajouter une catégorie';
}

function editSeries(id, name, img) {
    document.getElementById('serId').value = id;
    document.getElementById('serName').value = name;
    if (img) {
        document.getElementById('serPreview').src = 'uploads/' + img;
        document.getElementById('serPreview').style.display = 'block';
    }
    document.getElementById('serFormTitle').textContent = 'Modifier la série';
}

function resetSerForm() {
    document.getElementById('serId').value = '';
    document.getElementById('serName').value = '';
    document.getElementById('serPreview').style.display = 'none';
    document.getElementById('serFormTitle').textContent = 'Ajouter une série';
}

function editProduct(id, name, price, stock, cat, ser, img) {
    document.getElementById('prodId').value = id;
    document.getElementById('prodName').value = name;
    document.getElementById('prodPrice').value = price;
    document.getElementById('prodStock').value = stock;
    document.getElementById('prodCat').value = cat;
    document.getElementById('prodSer').value = ser;
    if (img) {
        document.getElementById('prodPreview').src = 'uploads/' + img;
        document.getElementById('prodPreview').style.display = 'block';
    }
    document.getElementById('prodFormTitle').textContent = 'Modifier le produit';
}

function resetProdForm() {
    document.getElementById('prodId').value = '';
    document.getElementById('prodName').value = '';
    document.getElementById('prodPrice').value = '';
    document.getElementById('prodStock').value = 0;
    document.getElementById('prodCat').value = '';
    document.getElementById('prodSer').value = '';
    document.getElementById('prodPreview').style.display = 'none';
    document.getElementById('prodFormTitle').textContent = 'Ajouter un produit';
}

function deleteItem(id, type) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer cet élément ?`)) {
        window.location.href = `?delete=${id}&type=${type}`;
    }
}

function updateStock(id, value) {
    fetch('update_stock.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&stock=${value}`
    })
    .then(response => response.text())
    .then(data => {
        dashboard.showNotification('Stock mis à jour avec succès');
    })
    .catch(() => {
        dashboard.showNotification('Erreur lors de la mise à jour', 'error');
    });
}

// Initialize dashboard
const dashboard = new ModernDashboard();

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
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
`;
document.head.appendChild(style);
</script>
</body>
</html>