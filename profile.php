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

// Handle Add / Edit / Delete (same as before)
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
<title>Profile - <?= htmlspecialchars($user['username']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="./assets/profile_style.css">
<style>
/* Enhanced Products Panel Styles */
.products-panel {
  position: fixed;
  top: -100%;
  left: 0;
  width: 100%;
  background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  transition: top .4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  z-index: 9999;
  padding: 25px 15px;
  border-bottom: 3px solid #00ffcc;
}

.products-panel.active {
  top: 70px;
}

.panel-inner {
  max-width: 1400px;
  margin: 0 auto;
  position: relative;
  min-height: 300px;
}

.panel-close {
  position: absolute;
  right: 15px;
  top: 0px;
  font-size: 32px;
  background: none;
  border: none;
  cursor: pointer;
  color: #666;
  transition: all 0.3s ease;
  z-index: 10;
}

.panel-close:hover {
  color: #ff4444;
  transform: scale(1.1);
}

.panel-title {
  text-align: center;
  margin-bottom: 30px;
  color: #333;
  font-size: 1.8em;
  font-weight: 700;
}

/* Dashboard Panel Styles */
.dashboard-panel {
  position: fixed;
  top: -100%;
  left: 0;
  width: 100%;
  background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.9));
  backdrop-filter: blur(20px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  transition: top .4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  z-index: 9998;
  padding: 25px 15px;
  border-bottom: 3px solid #667eea;
  max-height: 90vh;
  overflow-y: auto;
}

.dashboard-panel.active {
  top: 70px;
}

.dashboard-inner {
  max-width: 1400px;
  margin: 0 auto;
  position: relative;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid rgba(102, 126, 234, 0.2);
}

.dashboard-title {
  font-size: 2rem;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea, #764ba2);
 
  display: flex;
  align-items: center;
  gap: 15px;
}

.dashboard-close {
  background: none;
  border: none;
  font-size: 1.8rem;
  color: #718096;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 10px;
  transition: all 0.3s ease;
}

.dashboard-close:hover {
  color: #e53e3e;
  background: rgba(229, 62, 62, 0.1);
  transform: rotate(90deg);
}

/* Stats Cards in Dashboard */
.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.dashboard-stat-card {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 25px;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.dashboard-stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--gradient);
}

.dashboard-stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.dashboard-stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  font-size: 1.3rem;
  color: white;
  background: var(--gradient);
}

.dashboard-stat-number {
  font-size: 2rem;
  font-weight: 800;
  color: #2d3748;
  margin-bottom: 8px;
}

.dashboard-stat-label {
  color: #718096;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.dashboard-stat-card:nth-child(1) { --gradient: linear-gradient(135deg, #667eea, #764ba2); }
.dashboard-stat-card:nth-child(2) { --gradient: linear-gradient(135deg, #f093fb, #f5576c); }
.dashboard-stat-card:nth-child(3) { --gradient: linear-gradient(135deg, #4facfe, #00f2fe); }
.dashboard-stat-card:nth-child(4) { --gradient: linear-gradient(135deg, #43e97b, #38f9d7); }

/* Dashboard Quick Actions */
.dashboard-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
  margin-bottom: 30px;
}

.dashboard-action-btn {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border: none;
  border-radius: 15px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.dashboard-action-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
  color: white;
}

.dashboard-action-btn i {
  font-size: 1.5rem;
}

.dashboard-action-btn span {
  font-weight: 600;
  font-size: 0.9rem;
}

/* Categories Grid in Panel */
.panel-categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 25px;
  margin-bottom: 30px;
}

.panel-category-card {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 5px 20px rgba(0,0,0,0.1);
  transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  cursor: pointer;
  position: relative;
  text-decoration: none;
  color: inherit;
}

.panel-category-card:hover {
  transform: translateY(-10px) scale(1.02);
  box-shadow: 0 15px 40px rgba(0,255,204,0.2);
  text-decoration: none;
  color: inherit;
}

.panel-category-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(0,255,204,0.1) 0%, rgba(0,212,170,0.1) 100%);
  opacity: 0;
  transition: opacity 0.3s;
  z-index: 1;
}

.panel-category-card:hover::before {
  opacity: 1;
}

.panel-category-image {
  width: 100%;
  height: 180px;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.panel-category-card:hover .panel-category-image {
  transform: scale(1.1);
}

.panel-category-info {
  padding: 20px;
  position: relative;
  z-index: 2;
}

.panel-category-title {
  font-size: 1.3em;
  font-weight: 700;
  color: #333;
  margin-bottom: 8px;
  transition: color 0.3s;
}

.panel-category-card:hover .panel-category-title {
  color: #00ffcc;
}

.panel-category-count {
  color: #666;
  font-size: 0.9em;
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 15px;
}

.panel-category-count i {
  color: #00ffcc;
}

.panel-view-btn {
  background: linear-gradient(45deg, #00ffcc, #00d4aa);
  color: #1a1a1a;
  border: none;
  padding: 10px 18px;
  border-radius: 20px;
  font-size: 0.9em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
}

.panel-view-btn:hover {
  background: linear-gradient(45deg, #00d4aa, #00b894);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,255,204,0.3);
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
    max-width: 1000px;
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
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

/* Modern Categories Grid in Modal */
.modern-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 2rem;
}

.modern-category-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.modern-category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-color: #667eea;
}

.modern-category-image {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 15px;
}

.modern-category-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

.modern-category-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modern-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.modern-btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.modern-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.modern-btn-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
}

.modern-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
}

/* Form Styles */
.modern-form {
    display: grid;
    gap: 1.5rem;
    margin-top: 2rem;
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

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* Messages */
.message { 
    padding: 15px 20px; 
    margin: 20px auto; 
    border-radius: 10px; 
    max-width: 600px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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

/* Responsive */
@media (max-width: 768px) {
  .products-panel.active, .dashboard-panel.active {
    top: 60px;
  }
  
  .panel-categories-grid, .modern-categories-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .dashboard-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .dashboard-actions {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Animation classes */
.fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
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

/* Notification styles */
.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 20px;
  border-radius: 10px;
  z-index: 10001;
  font-weight: 500;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 10px;
}

.notification.success {
  background: linear-gradient(45deg, #28a745, #20c997);
  color: white;
}

.notification.error {
  background: linear-gradient(45deg, #dc3545, #e74c3c);
  color: white;
}

.notification.info {
  background: linear-gradient(45deg, #17a2b8, #007bff);
  color: white;
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(100%); opacity: 0; }
}
</style>
</head>
<body>

<header>
<div class="logo"><img src="uploads/ekoled2.png" width="100px" height="50px"></div>
<nav>
<a href="profile.php">Accueil</a>
<a href="#" id="productsToggle">Produits</a>
<a href="#" id="dashboardToggle">Dashboard</a>
<a href="#">À propos</a>
<a href="#" id="manageBtn">Gestion du stock</a>
</nav>
<div class="header-icons">
<input type="text" class="search-bar" placeholder="Rechercher..." id="productSearch">
<a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
<a href="#" id="userIcon"><i class="fas fa-user"></i></a>
</div>
<div class="user-menu" id="userMenu">
<p><strong>Utilisateur :</strong> <?= htmlspecialchars($user['username']) ?></p>
<p>Email : <?= htmlspecialchars($user['email']) ?></p>
<a href="logout.php">Déconnexion</a>
</div>
</header>

<!-- Enhanced Products Panel -->
<div id="productsPanel" class="products-panel" aria-hidden="true">
  <div class="panel-inner">
    <button id="productsPanelClose" class="panel-close" aria-label="Fermer">&times;</button>
    
    <h2 class="panel-title">
      <i class="fas fa-th-large"></i> Choisissez une Catégorie
    </h2>

    <!-- Categories Grid -->
    <div class="panel-categories-grid" id="panelCategoriesGrid">
      <?php foreach($categories as $index => $category): ?>
        <div class="panel-category-card fade-in-up" style="animation-delay: <?= $index * 0.1 ?>s;">
          <img src="uploads/<?= htmlspecialchars($category['image']) ?>" alt="<?= htmlspecialchars($category['name']) ?>" class="panel-category-image" onerror="this.src='https://via.placeholder.com/300x180/f0f0f0/999?text=<?= urlencode($category['name']) ?>'">
          <div class="panel-category-info">
            <h3 class="panel-category-title"><?= htmlspecialchars($category['name']) ?></h3>
            <div class="panel-category-count">
              <i class="fas fa-layer-group"></i>
              <?php
              $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.id) as series_count FROM series s JOIN products p ON s.id = p.series_id WHERE p.category_id = ?");
              $stmt->bind_param("i", $category['id']);
              $stmt->execute();
              $seriesCount = $stmt->get_result()->fetch_assoc()['series_count'];
              ?>
              <?= $seriesCount ?> série<?= $seriesCount > 1 ? 's' : '' ?> disponible<?= $seriesCount > 1 ? 's' : '' ?>
            </div>
            <button class="panel-view-btn" onclick="navigateToCategory(<?= $category['id'] ?>)">
              <i class="fas fa-arrow-right"></i>
              Voir les séries
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>

<!-- Dashboard Panel -->
<div id="dashboardPanel" class="dashboard-panel" aria-hidden="true">
  <div class="dashboard-inner">
    <div class="dashboard-header">
      <h2 class="dashboard-title">
        <i class="fas fa-tachometer-alt"></i>
        Dashboard EKOLED
      </h2>
      <button id="dashboardPanelClose" class="dashboard-close" aria-label="Fermer">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <!-- Stats Cards -->
    <div class="dashboard-stats">
      <div class="dashboard-stat-card fade-in-up" style="animation-delay: 0.1s;">
        <div class="dashboard-stat-icon">
          <i class="fas fa-th-large"></i>
        </div>
        <div class="dashboard-stat-number" data-target="<?= $stats['total_categories'] ?>"><?= $stats['total_categories'] ?></div>
        <div class="dashboard-stat-label">Catégories</div>
      </div>
      
      <div class="dashboard-stat-card fade-in-up" style="animation-delay: 0.2s;">
        <div class="dashboard-stat-icon">
          <i class="fas fa-layer-group"></i>
        </div>
        <div class="dashboard-stat-number" data-target="<?= $stats['total_series'] ?>"><?= $stats['total_series'] ?></div>
        <div class="dashboard-stat-label">Séries</div>
      </div>
      
      <div class="dashboard-stat-card fade-in-up" style="animation-delay: 0.3s;">
        <div class="dashboard-stat-icon">
          <i class="fas fa-boxes"></i>
        </div>
        <div class="dashboard-stat-number" data-target="<?= $stats['total_products'] ?>"><?= $stats['total_products'] ?></div>
        <div class="dashboard-stat-label">Produits</div>
      </div>
      
      <div class="dashboard-stat-card fade-in-up" style="animation-delay: 0.4s;">
        <div class="dashboard-stat-icon">
          <i class="fas fa-coins"></i>
        </div>
        <div class="dashboard-stat-number" data-target="<?= number_format($stats['total_stock_value'], 0) ?>"><?= number_format($stats['total_stock_value'], 0) ?></div>
        <div class="dashboard-stat-label">Valeur Stock (DT)</div>
      </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="dashboard-actions">
      <button class="dashboard-action-btn" onclick="showProductsPanel()">
        <i class="fas fa-eye"></i>
        <span>Parcourir Produits</span>
      </button>
      
      <a href="cart.php" class="dashboard-action-btn">
        <i class="fas fa-shopping-cart"></i>
        <span>Mon Panier</span>
      </a>
      
      <button class="dashboard-action-btn" onclick="openManageModal()">
        <i class="fas fa-cog"></i>
        <span>Gérer Stock</span>
      </button>
      
      <button class="dashboard-action-btn" onclick="showLowStockAlert()">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Stock Faible</span>
      </button>
    </div>
    
    <!-- Recent Activity -->
    <div style="background: rgba(255,255,255,0.9); border-radius: 15px; padding: 20px; margin-top: 20px;">
      <h3 style="margin-bottom: 15px; color: #2d3748; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-clock"></i>
        Activité Récente
      </h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <?php 
        $recentProducts = array_slice($products, 0, 4);
        foreach($recentProducts as $prod): 
        ?>
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; margin-bottom: 10px;">
          <h4 style="font-size: 0.9rem; margin-bottom: 5px; color: #2d3748;"><?= htmlspecialchars($prod['name']) ?></h4>
          <p style="font-size: 0.8rem; color: #718096; margin: 0;">
            <?= number_format($prod['price'], 2) ?> DT • Stock: <?= $prod['stock'] ?? 0 ?>
          </p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

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
<section class="hero">
<?php foreach($categories as $i => $cat): ?>
    <img src="uploads/<?= htmlspecialchars($cat['image']) ?>" class="<?= $i===0 ? 'active' : '' ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
<?php endforeach; ?>
<div class="hero-text">Une Nouvelle <br> Culture de Lumière</div>
<div class="hero-dots">
<?php foreach($categories as $i => $cat): ?>
    <span class="<?= $i===0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span>
<?php endforeach; ?>
</div>
</section>

<!-- Hero / Description -->
<section id="home-hero" style="text-align:center; padding:30px;">
    <h1>EKOLED</h1>
    <p>Bienvenue chez EKOLED – Une nouvelle culture de lumière, innovation et qualité pour tous vos projets d'éclairage.</p>
    <div class="hero-images" style="display:flex; justify-content:center; gap:20px; margin-top:20px;">
        <img src="uploads/1758235909_depot.jpg" alt="EKOLED 1" style="width:15%; border-radius:10px;">
        <img src="uploads/1758235909_depot.jpg" alt="EKOLED 2" style="width:15%; border-radius:10px;">
    </div>
</section>

<!-- Categories Section -->
<section id="home-categories" style="padding:30px; background:#f9f9f9;">
    <h2 style="text-align:center;">Catégories</h2>
    <div class="cards" style="display:flex; flex-wrap:wrap; justify-content:center; gap:20px; margin-top:20px;">
        <?php foreach($categories as $cat): ?>
        <div class="card" style="border:1px solid #ddd; border-radius:10px; padding:10px; width:200px; text-align:center; cursor: pointer;" onclick="navigateToCategory(<?= $cat['id'] ?>)">
            <img src="uploads/<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:5px;">
            <h3><?= htmlspecialchars($cat['name']) ?></h3>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Products Section -->
<section id="home-products" style="padding:30px;">
    <h2 style="text-align:center;">Produits</h2>
    <div class="cards" style="display:flex; flex-wrap:wrap; justify-content:center; gap:20px; margin-top:20px;">
        <?php foreach($products as $prod): ?>
        <div class="card" style="border:1px solid #ddd; border-radius:10px; padding:10px; width:200px; text-align:center;">
            <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:5px;">
            <h3><?= htmlspecialchars($prod['name']) ?></h3>
            <p>Prix: <?= number_format($prod['price'], 2) ?> DT</p>
            <p>Catégorie: <?= htmlspecialchars($prod['category_name'] ?? '---') ?></p>
            <p>Série: <?= htmlspecialchars($prod['series_name'] ?? '---') ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

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
            <div class="modern-categories-grid">
                <?php foreach ($categories as $cat): ?>
                <div class="modern-category-card">
                    <img src="uploads/<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="modern-category-image" onerror="this.src='https://via.placeholder.com/280x120/f0f0f0/999?text=<?= urlencode($cat['name']) ?>'">
                    <h3 class="modern-category-name"><?= htmlspecialchars($cat['name']) ?></h3>
                    <div class="modern-category-actions">
                        <button class="modern-btn modern-btn-primary" onclick="editCategory(<?= $cat['id'] ?>,'<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>','<?= $cat['image'] ?>')">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button class="modern-btn modern-btn-danger" onclick="deleteItem(<?= $cat['id'] ?>, 'category', '<?= htmlspecialchars($cat['name']) ?>')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 id="catFormTitle">Ajouter une Catégorie</h3>
            <form method="POST" enctype="multipart/form-data" id="catForm" class="modern-form">
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
                
                <div class="form-actions">
                    <button type="submit" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <button type="button" class="modern-btn" onclick="resetCatForm()" style="background: #e2e8f0; color: #4a5568;">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>

        <!-- Series Tab -->
        <div class="tab-content" id="series">
            <h3>Séries Existantes</h3>
            <div class="modern-categories-grid">
                <?php foreach ($series as $ser): ?>
                <div class="modern-category-card">
                    <img src="uploads/<?= htmlspecialchars($ser['image']) ?>" alt="<?= htmlspecialchars($ser['name']) ?>" class="modern-category-image" onerror="this.src='https://via.placeholder.com/280x120/f0f0f0/999?text=<?= urlencode($ser['name']) ?>'">
                    <h3 class="modern-category-name"><?= htmlspecialchars($ser['name']) ?></h3>
                    <div class="modern-category-actions">
                        <button class="modern-btn modern-btn-primary" onclick="editSeries(<?= $ser['id'] ?>,'<?= htmlspecialchars($ser['name'], ENT_QUOTES) ?>','<?= $ser['image'] ?>')">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button class="modern-btn modern-btn-danger" onclick="deleteItem(<?= $ser['id'] ?>, 'series', '<?= htmlspecialchars($ser['name']) ?>')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 id="serFormTitle">Ajouter une Série</h3>
            <form method="POST" enctype="multipart/form-data" id="serForm" class="modern-form">
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
                
                <div class="form-actions">
                    <button type="submit" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <button type="button" class="modern-btn" onclick="resetSerForm()" style="background: #e2e8f0; color: #4a5568;">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Tab -->
        <div class="tab-content" id="products">
            <div class="form-group" style="margin-bottom: 2rem;">
                <input type="text" id="searchInput" class="form-input" placeholder="Rechercher un produit...">
            </div>
            
            <div class="modern-categories-grid" id="productsList">
                <?php foreach ($products as $prod): ?>
                <div class="modern-category-card" data-name="<?= htmlspecialchars(strtolower($prod['name'])) ?>">
                    <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="modern-category-image" onerror="this.src='https://via.placeholder.com/280x120/f0f0f0/999?text=<?= urlencode($prod['name']) ?>'">
                    <h3 class="modern-category-name"><?= htmlspecialchars($prod['name']) ?></h3>
                    <p style="color: #718096; margin: 10px 0;">
                        <?= number_format($prod['price'], 2) ?> DT<br>
                        Stock: 
                        <input type="number" value="<?= intval($prod['stock'] ?? 0) ?>" 
                               style="width:60px; padding: 0.25rem; border: 1px solid #e2e8f0; border-radius: 4px; text-align: center;" 
                               onchange="updateStock(<?= $prod['id'] ?>, this.value)">
                    </p>
                    <div class="modern-category-actions">
                        <button class="modern-btn modern-btn-primary" onclick="editProduct(<?= $prod['id'] ?>,'<?= htmlspecialchars($prod['name'],ENT_QUOTES) ?>',<?= $prod['price'] ?>,<?= intval($prod['stock'] ?? 0) ?>,<?= $prod['category_id'] ?? 0 ?>,<?= $prod['series_id'] ?? 0 ?>,'<?= $prod['image'] ?>')">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button class="modern-btn modern-btn-danger" onclick="deleteItem(<?= $prod['id'] ?>, 'product', '<?= htmlspecialchars($prod['name']) ?>')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 id="prodFormTitle">Ajouter un Produit</h3>
            <form method="POST" enctype="multipart/form-data" id="prodForm" class="modern-form">
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
                
                <div class="form-actions">
                    <button type="submit" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <button type="button" class="modern-btn" onclick="resetProdForm()" style="background: #e2e8f0; color: #4a5568;">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="footer-logo">
        <img src="uploads/ekoled2.png" alt="EKOLED Logo">
    </div>
    <div class="footer-categories">
        <?php foreach($categories as $cat): ?>
            <a href="category.php?id=<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</footer>

<script>
// Enhanced Products Panel Navigation
class ProductsNavigation {
  constructor() {
    this.panel = document.getElementById('productsPanel');
    this.closeBtn = document.getElementById('productsPanelClose');
    this.dashboardPanel = document.getElementById('dashboardPanel');
    this.dashboardCloseBtn = document.getElementById('dashboardPanelClose');
    
    this.bindEvents();
  }
  
  bindEvents() {
    // Products panel toggle
    document.getElementById('productsToggle')?.addEventListener('click', (e) => {
      e.preventDefault();
      this.toggleProductsPanel();
    });
    
    // Dashboard panel toggle
    document.getElementById('dashboardToggle')?.addEventListener('click', (e) => {
      e.preventDefault();
      this.toggleDashboardPanel();
    });
    
    // Close buttons
    this.closeBtn?.addEventListener('click', () => this.closeProductsPanel());
    this.dashboardCloseBtn?.addEventListener('click', () => this.closeDashboardPanel());
    
    // Close on outside click or Escape
    document.addEventListener('click', (e) => {
      if (this.panel?.classList.contains('active') && 
          !e.target.closest('#productsPanel') && 
          !e.target.closest('#productsToggle')) {
        this.closeProductsPanel();
      }
      
      if (this.dashboardPanel?.classList.contains('active') && 
          !e.target.closest('#dashboardPanel') && 
          !e.target.closest('#dashboardToggle')) {
        this.closeDashboardPanel();
      }
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeProductsPanel();
        this.closeDashboardPanel();
      }
    });
  }
  
  toggleProductsPanel() {
    if (this.panel?.classList.contains('active')) {
      this.closeProductsPanel();
    } else {
      this.closeDashboardPanel(); // Close dashboard if open
      this.openProductsPanel();
    }
  }
  
  toggleDashboardPanel() {
    if (this.dashboardPanel?.classList.contains('active')) {
      this.closeDashboardPanel();
    } else {
      this.closeProductsPanel(); // Close products if open
      this.openDashboardPanel();
    }
  }
  
  openProductsPanel() {
    this.panel?.classList.add('active');
    this.panel?.setAttribute('aria-hidden', 'false');
  }
  
  closeProductsPanel() {
    this.panel?.classList.remove('active');
    this.panel?.setAttribute('aria-hidden', 'true');
  }
  
  openDashboardPanel() {
    this.dashboardPanel?.classList.add('active');
    this.dashboardPanel?.setAttribute('aria-hidden', 'false');
    this.animateStats();
  }
  
  closeDashboardPanel() {
    this.dashboardPanel?.classList.remove('active');
    this.dashboardPanel?.setAttribute('aria-hidden', 'true');
  }
  
  animateStats() {
    // Animate numbers when dashboard opens
    setTimeout(() => {
      document.querySelectorAll('.dashboard-stat-number').forEach(stat => {
        const target = parseInt(stat.dataset.target || stat.textContent.replace(/,/g, ''));
        this.animateNumber(stat, target);
      });
    }, 300);
  }
  
  animateNumber(element, target) {
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
}

// Navigation functions
function navigateToCategory(categoryId) {
  showNotification('Chargement des séries...', 'info');
  setTimeout(() => {
    window.location.href = `category.php?id=${categoryId}`;
  }, 500);
}

function showProductsPanel() {
  productsNav.openProductsPanel();
}

function showLowStockAlert() {
  // Show products with low stock (example: stock <= 5)
  const lowStockProducts = <?= json_encode(array_filter($products, function($p) { return ($p['stock'] ?? 0) <= 5; })) ?>;
  
  if (lowStockProducts.length === 0) {
    showNotification('Aucun produit en stock faible détecté !', 'success');
    return;
  }
  
  let message = `⚠️ ${lowStockProducts.length} produit(s) en stock faible:\n`;
  lowStockProducts.slice(0, 3).forEach(p => {
    message += `• ${p.name}: ${p.stock || 0} unités\n`;
  });
  
  if (lowStockProducts.length > 3) {
    message += `... et ${lowStockProducts.length - 3} autres`;
  }
  
  alert(message);
}

// Show notification function
function showNotification(message, type = 'success') {
  document.querySelectorAll('.notification').forEach(notif => notif.remove());
  
  const notification = document.createElement('div');
  notification.className = `notification ${type}`;
  notification.innerHTML = `
    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
    ${message}
  `;
  notification.style.animation = 'slideInRight 0.3s ease';
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Initialize the navigation system
const productsNav = new ProductsNavigation();

// User menu toggle
document.getElementById('userIcon').addEventListener('click', e => {
  e.preventDefault();
  document.getElementById('userMenu').classList.toggle('active');
});

// Modal management
function openManageModal() {
  document.getElementById('manageModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeManageModal() {
  document.getElementById('manageModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

document.getElementById('manageBtn').addEventListener('click', (e) => {
  e.preventDefault();
  openManageModal();
});

// Tab functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

// Hero Slider
let heroImgs = document.querySelectorAll('.hero img');
let dots = document.querySelectorAll('.hero-dots span');
let current = 0;
function showSlide(n){
  heroImgs.forEach(img=>img.classList.remove('active'));
  dots.forEach(d=>d.classList.remove('active'));
  heroImgs[n].classList.add('active');
  dots[n].classList.add('active');
  current=n;
}
dots.forEach((dot,i)=>dot.addEventListener('click',()=>showSlide(i)));
setInterval(()=>{ showSlide((current+1)%heroImgs.length); },5000);

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

function deleteItem(id, type, name) {
  if (confirm(`Êtes-vous sûr de vouloir supprimer "${name}" ?`)) {
    showNotification('Suppression en cours...', 'info');
    setTimeout(() => {
      window.location.href = `?delete=${id}&type=${type}`;
    }, 500);
  }
}

function updateStock(id, value) {
  const originalValue = value;
  
  fetch('update_stock.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `id=${id}&stock=${value}`
  })
  .then(response => response.text())
  .then(data => {
    showNotification('Stock mis à jour avec succès', 'success');
    
    // Update dashboard stats if open
    if (document.getElementById('dashboardPanel').classList.contains('active')) {
      // Recalculate stats (simplified - in real app you'd fetch from server)
      setTimeout(() => {
        location.reload(); // Refresh to get updated stats
      }, 1000);
    }
  })
  .catch(() => {
    showNotification('Erreur lors de la mise à jour', 'error');
  });
}

// Product search/filter
document.getElementById('searchInput')?.addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const products = document.querySelectorAll('#productsList .modern-category-card');
  
  products.forEach(product => {
    const name = product.dataset.name || '';
    const isVisible = name.includes(searchTerm);
    product.style.display = isVisible ? 'block' : 'none';
  });
});

// Search functionality in products panel
document.getElementById('productSearch')?.addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const cards = document.querySelectorAll('.panel-category-card');
  
  cards.forEach(card => {
    const title = card.querySelector('.panel-category-title');
    if (title && title.textContent.toLowerCase().includes(searchTerm)) {
      card.style.display = 'block';
      card.style.opacity = '1';
      card.style.transform = 'scale(1)';
    } else if (searchTerm === '') {
      card.style.display = 'block';
      card.style.opacity = '1';
      card.style.transform = 'scale(1)';
    } else {
      card.style.opacity = '0.3';
      card.style.transform = 'scale(0.95)';
    }
  });
});

// Enhanced keyboard shortcuts
document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + D for Dashboard
  if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
    e.preventDefault();
    productsNav.toggleDashboardPanel();
  }
  
  // Ctrl/Cmd + P for Products
  if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
    e.preventDefault();
    productsNav.toggleProductsPanel();
  }
  
  // Ctrl/Cmd + M for Manage
  if ((e.ctrlKey || e.metaKey) && e.key === 'm') {
    e.preventDefault();
    openManageModal();
  }
});

// Add smooth page transitions
document.addEventListener('DOMContentLoaded', function() {
  // Fade in page content
  document.body.style.opacity = '0';
  document.body.style.transition = 'opacity 0.5s ease';
  
  setTimeout(() => {
    document.body.style.opacity = '1';
  }, 100);
  
  // Initialize tooltips for keyboard shortcuts
  const shortcuts = [
    { element: '#dashboardToggle', text: 'Raccourci: Ctrl+D' },
    { element: '#productsToggle', text: 'Raccourci: Ctrl+P' },
    { element: '#manageBtn', text: 'Raccourci: Ctrl+M' }
  ];
  
  shortcuts.forEach(shortcut => {
    const element = document.querySelector(shortcut.element);
    if (element) {
      element.title = shortcut.text;
    }
  });
});

// Page transition on navigation
window.addEventListener('beforeunload', function() {
  document.body.style.opacity = '0';
});

// Auto-refresh dashboard stats every 5 minutes
setInterval(() => {
  if (document.getElementById('dashboardPanel').classList.contains('active')) {
    // In a real application, you'd fetch updated stats via AJAX
    console.log('Dashboard stats would be refreshed here');
  }
}, 300000); // 5 minutes

// Performance monitoring (optional)
let startTime = performance.now();
window.addEventListener('load', () => {
  const loadTime = performance.now() - startTime;
  console.log(`Page loaded in ${Math.round(loadTime)}ms`);
});

// Add advanced notification types
function showAdvancedNotification(message, type = 'success', duration = 3000) {
  document.querySelectorAll('.notification').forEach(notif => notif.remove());
  
  const notification = document.createElement('div');
  notification.className = `notification ${type}`;
  
  const icons = {
    success: 'check-circle',
    error: 'exclamation-circle',
    warning: 'exclamation-triangle',
    info: 'info-circle',
    loading: 'spinner fa-spin'
  };
  
  notification.innerHTML = `
    <i class="fas fa-${icons[type] || 'info-circle'}"></i>
    <span>${message}</span>
    ${duration > 0 ? '<button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;margin-left:10px;cursor:pointer;"><i class="fas fa-times"></i></button>' : ''}
  `;
  
  notification.style.animation = 'slideInRight 0.3s ease';
  document.body.appendChild(notification);
  
  if (duration > 0) {
    setTimeout(() => {
      if (notification.parentElement) {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
      }
    }, duration);
  }
  
  return notification;
}

// Welcome message on first load
if (!sessionStorage.getItem('welcomed')) {
  setTimeout(() => {
    showAdvancedNotification('Bienvenue sur EKOLED Dashboard ! 🎉', 'info', 5000);
    sessionStorage.setItem('welcomed', 'true');
  }, 1000);
}

console.log('🚀 EKOLED Profile Dashboard Loaded Successfully!');
console.log('💡 Raccourcis clavier disponibles:');
console.log('   • Ctrl+D: Ouvrir Dashboard');
console.log('   • Ctrl+P: Ouvrir Produits');
console.log('   • Ctrl+M: Gestion du stock');
console.log('   • Escape: Fermer les panneaux');
</script>
</body>
</html>  