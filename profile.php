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

// Get data
$categories = $conn->query("SELECT * FROM categories ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$series = $conn->query("SELECT * FROM series ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("
    SELECT p.*, c.name AS category_name, s.name AS series_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN series s ON p.series_id = s.id
    ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);
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

/* Loading animation for panel */
.panel-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.panel-loading.active {
  opacity: 1;
}

.panel-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f0f0f0;
  border-top: 4px solid #00ffcc;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Animation classes */
.slide-in-down {
  animation: slideInDown 0.5s ease-out;
}

@keyframes slideInDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in-scale {
  animation: fadeInScale 0.4s ease-out;
}

@keyframes fadeInScale {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .products-panel.active {
    top: 60px;
  }
  
  .panel-categories-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .panel-inner {
    padding: 0 10px;
  }
  
  .panel-title {
    font-size: 1.5em;
  }
}

/* Messages */
.message { padding:10px; margin:10px 0; border-radius:5px; }
.message.success { background:#d4edda; color:#155724; }
.message.error { background:#f8d7da; color:#721c24; }

/* Notification styles */
.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 20px;
  border-radius: 8px;
  z-index: 10000;
  font-weight: 500;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
  transition: all 0.3s ease;
}

.notification.success {
  background: linear-gradient(45deg, #28a745, #20c997);
  color: white;
}

.notification.error {
  background: linear-gradient(45deg, #dc3545, #e74c3c);
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
<a href="#">Aperçu</a>
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
    
    <h2 class="panel-title slide-in-down">
      <i class="fas fa-th-large"></i> Choisissez une Catégorie
    </h2>

    <!-- Loading Animation -->
    <div class="panel-loading" id="panelLoadingSpinner">
      <div class="panel-spinner"></div>
    </div>

    <!-- Categories Grid -->
    <div class="panel-categories-grid" id="panelCategoriesGrid">
      <!-- Categories will be populated here -->
    </div>
  </div>
</div>

<!-- Messages -->
<?php if($success): ?><div class="message success"><?= $success ?></div><?php endif; ?>
<?php if($error): ?><div class="message error"><?= $error ?></div><?php endif; ?>

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
        <div class="card" style="border:1px solid #ddd; border-radius:10px; padding:10px; width:200px; text-align:center;">
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

<!-- Modal Gestion Stock (keeping original modal for admin) -->
<div id="manageModal" class="modal">
<div class="modal-content">
<span class="close">&times;</span>
<h2>Gestion du stock</h2>
<div class="tabs">
<button class="tab-btn active" data-tab="categories">Catégories</button>
<button class="tab-btn" data-tab="series">Séries</button>
<button class="tab-btn" data-tab="products">Produits</button>
</div>

<!-- Categories Tab -->
<div class="tab-content active" id="categories">
<h3>Catégories existantes</h3>
<ul>
<?php foreach ($categories as $cat): ?>
<li>
<?= htmlspecialchars($cat['name']) ?>
<img src="uploads/<?= htmlspecialchars($cat['image']) ?>" width="50">
<a href="?delete=<?= $cat['id'] ?>&type=category" class="delete-btn">Supprimer</a>
<span class="edit-btn" onclick="editCategory(<?= $cat['id'] ?>,'<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>','<?= $cat['image'] ?>')">Modifier</span>
</li>
<?php endforeach; ?>
</ul>

<h3 id="catFormTitle">Ajouter une catégorie</h3>
<img id="catPreview" src="" width="80" style="display:none; margin-bottom:10px;">
<form method="POST" enctype="multipart/form-data" id="catForm">
<input type="hidden" name="action" value="category">
<input type="hidden" name="id" id="catId">
<input type="text" name="name" placeholder="Nom catégorie" id="catName" required>
<input type="file" name="image">
<button type="submit">Enregistrer</button>
<button type="button" onclick="resetCatForm()">Annuler</button>
</form>
</div>

<!-- Series Tab -->
<div class="tab-content" id="series">
<h3>Séries existantes</h3>
<ul>
<?php foreach ($series as $ser): ?>
<li>
<?= htmlspecialchars($ser['name']) ?>
<img src="uploads/<?= htmlspecialchars($ser['image']) ?>" width="50">
<a href="?delete=<?= $ser['id'] ?>&type=series" class="delete-btn">Supprimer</a>
<span class="edit-btn" onclick="editSeries(<?= $ser['id'] ?>,'<?= htmlspecialchars($ser['name'], ENT_QUOTES) ?>','<?= $ser['image'] ?>')">Modifier</span>
</li>
<?php endforeach; ?>
</ul>

<h3 id="serFormTitle">Ajouter une série</h3>
<img id="serPreview" src="" width="80" style="display:none; margin-bottom:10px;">
<form method="POST" enctype="multipart/form-data" id="serForm">
<input type="hidden" name="action" value="series">
<input type="hidden" name="id" id="serId">
<input type="text" name="name" placeholder="Nom série" id="serName" required>
<input type="file" name="image">
<button type="submit">Enregistrer</button>
<button type="button" onclick="resetSerForm()">Annuler</button>
</form>
</div>

<!-- Products Tab -->
<div class="tab-content" id="products">
<h3>Produits existants</h3>
<input type="text" id="searchInput" placeholder="Rechercher produit...">
<ul id="productList">
<?php foreach ($products as $prod): ?>
<li data-name="<?= htmlspecialchars(strtolower($prod['name'])) ?>" data-cat="<?= $prod['category_id'] ?>" data-ser="<?= $prod['series_id'] ?>">
<?= htmlspecialchars($prod['name']) ?> - <?= number_format($prod['price'],2) ?> DT
Stock: <input type="number" value="<?= intval($prod['stock'] ?? 0) ?>" style="width:50px;" onchange="updateStock(<?= $prod['id'] ?>, this.value)">
(Catégorie: <?= htmlspecialchars($prod['category_name'] ?? '---') ?>, Série: <?= htmlspecialchars($prod['series_name'] ?? '---') ?>)
<img src="uploads/<?= htmlspecialchars($prod['image']) ?>" width="50">
<a href="?delete=<?= $prod['id'] ?>&type=product" class="delete-btn">Supprimer</a>
<span class="edit-btn" onclick="editProduct(<?= $prod['id'] ?>,'<?= htmlspecialchars($prod['name'],ENT_QUOTES) ?>',<?= $prod['price'] ?>,<?= intval($prod['stock'] ?? 0) ?>,<?= $prod['category_id'] ?? 0 ?>,<?= $prod['series_id'] ?? 0 ?>,'<?= $prod['image'] ?>')">Modifier</span>
</li>
<?php endforeach; ?>
</ul>

<h3 id="prodFormTitle">Ajouter un produit</h3>
<img id="prodPreview" src="" width="80" style="display:none; margin-bottom:10px;">
<form method="POST" enctype="multipart/form-data" id="prodForm">
<input type="hidden" name="action" value="product">
<input type="hidden" name="id" id="prodId">
<input type="text" name="name" placeholder="Nom produit" id="prodName" required>
<input type="number" step="0.01" name="price" placeholder="Prix" id="prodPrice" required>
<input type="number" name="stock" placeholder="Stock" id="prodStock" value="0" required>
<select name="category_id" id="prodCat" required>
<option value="">-- Choisir catégorie --</option>
<?php foreach ($categories as $cat): ?>
<option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
<?php endforeach; ?>
</select>
<select name="series_id" id="prodSer">
<option value="">-- (Optionnel) Choisir série --</option>
<?php foreach ($series as $ser): ?>
<option value="<?= $ser['id'] ?>"><?= htmlspecialchars($ser['name']) ?></option>
<?php endforeach; ?>
</select>
<input type="file" name="image">
<button type="submit">Enregistrer</button>
<button type="button" onclick="resetProdForm()">Annuler</button>
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
    this.loadingSpinner = document.getElementById('panelLoadingSpinner');
    this.categoriesGrid = document.getElementById('panelCategoriesGrid');
    
    this.bindEvents();
  }
  
  bindEvents() {
    // Panel toggle
    document.getElementById('productsToggle')?.addEventListener('click', (e) => {
      e.preventDefault();
      this.togglePanel();
    });
    
    // Close panel
    this.closeBtn?.addEventListener('click', () => this.closePanel());
    
    // Close on outside click or Escape
    window.addEventListener('click', (e) => {
      if (this.panel?.classList.contains('active') && 
          !e.target.closest('#productsPanel') && 
          !e.target.closest('#productsToggle')) {
        this.closePanel();
      }
    });
    
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.panel?.classList.contains('active')) {
        this.closePanel();
      }
    });
  }
  
  togglePanel() {
    if (this.panel?.classList.contains('active')) {
      this.closePanel();
    } else {
      this.openPanel();
    }
  }
  
  openPanel() {
    this.panel?.classList.add('active');
    this.panel?.setAttribute('aria-hidden', 'false');
    this.loadCategories();
  }
  
  closePanel() {
    this.panel?.classList.remove('active');
    this.panel?.setAttribute('aria-hidden', 'true');
  }
  
  showLoading() {
    this.loadingSpinner?.classList.add('active');
    this.categoriesGrid.style.display = 'none';
  }
  
  hideLoading() {
    this.loadingSpinner?.classList.remove('active');
    this.categoriesGrid.style.display = 'grid';
  }
  
  async loadCategories() {
    this.showLoading();
    
    try {
      // Fetch categories with series count
      const response = await fetch('api/get_categories.php');
      const data = await response.json();
      
      if (data.success) {
        this.renderCategories(data.data);
      } else {
        console.error('Error loading categories:', data.error);
        // Fallback to PHP data
        this.renderCategoriesFromPHP();
      }
      
    } catch (error) {
      console.error('Network error loading categories:', error);
      // Fallback to PHP data
      this.renderCategoriesFromPHP();
    } finally {
      this.hideLoading();
    }
  }
  
  // Fallback method using PHP data
  renderCategoriesFromPHP() {
    const categories = <?= json_encode($categories) ?>;
    this.renderCategories(categories.map(cat => ({
      id: cat.id,
      name: cat.name,
      image: cat.image,
      seriesCount: 0 // Will be calculated if needed
    })));
  }
  
  renderCategories(categories) {
    this.categoriesGrid.innerHTML = categories.map((category, index) => `
      <div class="panel-category-card fade-in-scale" style="animation-delay: ${index * 0.1}s;">
        <img src="uploads/${category.image}" alt="${category.name}" class="panel-category-image" onerror="this.src='https://via.placeholder.com/300x180/f0f0f0/999?text=${encodeURIComponent(category.name)}'">
        <div class="panel-category-info">
          <h3 class="panel-category-title">${category.name}</h3>
          <div class="panel-category-count">
            <i class="fas fa-layer-group"></i>
            ${category.seriesCount || 'Plusieurs'} série${(category.seriesCount || 2) > 1 ? 's' : ''} disponible${(category.seriesCount || 2) > 1 ? 's' : ''}
          </div>
          <button class="panel-view-btn" onclick="navigateToCategory(${category.id})">
            <i class="fas fa-arrow-right"></i>
            Voir les séries
          </button>
        </div>
      </div>
    `).join('');
    
    // Add stagger animation
    setTimeout(() => {
      this.categoriesGrid.classList.add('slide-in-down');
    }, 100);
  }
}

// Navigate to category page
function navigateToCategory(categoryId) {
  // Show loading effect
  showNotification('Chargement des séries...', 'info');
  
  // Add smooth transition effect
  setTimeout(() => {
    window.location.href = `category.php?id=${categoryId}`;
  }, 500);
}

// Show notification function
function showNotification(message, type = 'success') {
  // Remove existing notifications
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

// Add notification styles for info type
const style = document.createElement('style');
style.textContent = `
  .notification.info {
    background: linear-gradient(45deg, #17a2b8, #007bff);
    color: white;
  }
`;
document.head.appendChild(style);

// Initialize the navigation system
const productsNav = new ProductsNavigation();

// User menu toggle
document.getElementById('userIcon').addEventListener('click', e => {
  e.preventDefault();
  document.getElementById('userMenu').classList.toggle('active');
});

// Modal open/close for admin management
let modal = document.getElementById("manageModal");
let btn = document.getElementById("manageBtn");
let span = document.querySelector(".close");
btn.onclick = e => { e.preventDefault(); modal.style.display = "flex"; }
span.onclick = () => { modal.style.display = "none"; }
window.onclick = e => { if (e.target==modal) modal.style.display = "none"; }

// Tabs for admin management
let tabs = document.querySelectorAll(".tab-btn");
let contents = document.querySelectorAll(".tab-content");
tabs.forEach(tab=>{
  tab.addEventListener("click", ()=>{
    tabs.forEach(t=>t.classList.remove("active"));
    contents.forEach(c=>c.classList.remove("active"));
    tab.classList.add("active");
    document.getElementById(tab.dataset.tab).classList.add("active");
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

// Edit functions for admin management
function editCategory(id,name,img){
  document.getElementById('catId').value=id;
  document.getElementById('catName').value=name;
  document.getElementById('catPreview').src='uploads/'+img;
  document.getElementById('catPreview').style.display='block';
  document.getElementById('catFormTitle').innerText="Modifier la catégorie";
}
function resetCatForm(){
  document.getElementById('catId').value='';
  document.getElementById('catName').value='';
  document.getElementById('catPreview').style.display='none';
  document.getElementById('catFormTitle').innerText="Ajouter une catégorie";
}

function editSeries(id,name,img){
  document.getElementById('serId').value=id;
  document.getElementById('serName').value=name;
  document.getElementById('serPreview').src='uploads/'+img;
  document.getElementById('serPreview').style.display='block';
  document.getElementById('serFormTitle').innerText="Modifier la série";
}
function resetSerForm(){
  document.getElementById('serId').value='';
  document.getElementById('serName').value='';
  document.getElementById('serPreview').style.display='none';
  document.getElementById('serFormTitle').innerText="Ajouter une série";
}

function editProduct(id,name,price,stock,cat,ser,img){
  document.getElementById('prodId').value=id;
  document.getElementById('prodName').value=name;
  document.getElementById('prodPrice').value=price;
  document.getElementById('prodStock').value=stock;
  document.getElementById('prodCat').value=cat;
  document.getElementById('prodSer').value=ser;
  document.getElementById('prodPreview').src='uploads/'+img;
  document.getElementById('prodPreview').style.display='block';
  document.getElementById('prodFormTitle').innerText="Modifier le produit";
}
function resetProdForm(){
  document.getElementById('prodId').value='';
  document.getElementById('prodName').value='';
  document.getElementById('prodPrice').value='';
  document.getElementById('prodStock').value=0;
  document.getElementById('prodCat').value='';
  document.getElementById('prodSer').value='';
  document.getElementById('prodPreview').style.display='none';
  document.getElementById('prodFormTitle').innerText="Ajouter un produit";
}

// Product search/filter for admin
document.getElementById('searchInput').addEventListener('input', function(){
  let val=this.value.toLowerCase();
  document.querySelectorAll('#productList li').forEach(li=>{
    let name=li.dataset.name;
    li.style.display=name.includes(val)?'block':'none';
  });
});

// Update stock inline for admin
function updateStock(id,value){
  fetch('update_stock.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'id='+id+'&stock='+value
  }).then(res=>res.text()).then(data=>console.log(data));
}

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

// Add smooth page transitions
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
</script>
</body>
</html>