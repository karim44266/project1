<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get category info
$category = null;
if ($categoryId > 0) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    
    if (!$category) {
        header('Location: profile.php');
        exit();
    }
}

// Get series for this category with product counts
$series = [];
if ($categoryId > 0) {
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.image,
            COUNT(p.id) as product_count
        FROM series s
        LEFT JOIN products p ON s.id = p.series_id AND p.category_id = ?
        WHERE s.id IN (SELECT DISTINCT series_id FROM products WHERE category_id = ? AND series_id IS NOT NULL)
        GROUP BY s.id, s.name, s.image
        ORDER BY s.name ASC
    ");
    $stmt->bind_param("ii", $categoryId, $categoryId);
    $stmt->execute();
    $series = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
<title><?= $category ? htmlspecialchars($category['name']) . ' - Séries' : 'Catégorie' ?> - EKOLED</title>
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
    background: linear-gradient(135deg, rgba(0,255,204,0.9), rgba(0,212,170,0.9)), url('uploads/<?= $category ? htmlspecialchars($category['image']) : 'default.jpg' ?>');
    background-size: cover;
    background-position: center;
    color: white;
    padding: 60px 30px;
    text-align: center;
    margin-bottom: 40px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,255,204,0.3);
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
    background: rgba(0,0,0,0.2);
    z-index: 1;
}

.page-header-content {
    position: relative;
    z-index: 2;
}

.page-header h1 {
    font-size: 3em;
    margin: 0 0 15px 0;
    text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
    font-weight: 800;
}

.page-header p {
    font-size: 1.3em;
    opacity: 0.95;
    margin: 0;
    text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
    padding: 15px 25px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    font-size: 14px;
    transition: all 0.3s ease;
}

.breadcrumb:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.breadcrumb-item {
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
    padding: 5px 10px;
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
}

/* Series Grid */
.series-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.series-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 6px 25px rgba(0,0,0,0.1);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    cursor: pointer;
    position: relative;
    border: 2px solid transparent;
}

.series-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,255,204,0.2);
    border-color: #00ffcc;
}

.series-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(0,255,204,0.05) 0%, rgba(0,212,170,0.05) 100%);
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 1;
}

.series-card:hover::before {
    opacity: 1;
}

.series-image-container {
    position: relative;
    overflow: hidden;
    height: 220px;
}

.series-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.series-card:hover .series-image {
    transform: scale(1.1);
}

.series-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
    opacity: 0;
    transition: opacity 0.3s;
}

.series-card:hover .series-overlay {
    opacity: 1;
}

.series-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0,255,204,0.9);
    color: #1a1a1a;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.series-info {
    padding: 25px;
    position: relative;
    z-index: 2;
}

.series-title {
    font-size: 1.4em;
    font-weight: 700;
    color: #333;
    margin-bottom: 12px;
    transition: color 0.3s;
    line-height: 1.3;
}

.series-card:hover .series-title {
    color: #00ffcc;
}

.series-description {
    color: #666;
    font-size: 0.9em;
    line-height: 1.5;
    margin-bottom: 15px;
    height: 40px;
    overflow: hidden;
}

.series-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.series-count {
    color: #666;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.series-count i {
    color: #00ffcc;
}

.series-price-range {
    color: #00ffcc;
    font-weight: 600;
    font-size: 0.9em;
}

.view-products-btn {
    background: linear-gradient(45deg, #00ffcc, #00d4aa);
    color: #1a1a1a;
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    text-decoration: none;
    font-size: 0.95em;
}

.view-products-btn:hover {
    background: linear-gradient(45deg, #00d4aa, #00b894);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,255,204,0.3);
    color: #1a1a1a;
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

.back-to-home {
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

.back-to-home:hover {
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
        padding: 40px 20px;
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        font-size: 2.2em;
    }
    
    .page-header p {
        font-size: 1.1em;
    }
    
    .series-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .breadcrumb {
        padding: 12px 20px;
        font-size: 13px;
        flex-wrap: wrap;
    }
    
    .series-card {
        margin: 0 5px;
    }
    
    .series-info {
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.8em;
    }
    
    .page-header p {
        font-size: 1em;
    }
    
    .series-image-container {
        height: 180px;
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
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Produits</span>
        <?php if ($category): ?>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-item active"><?= htmlspecialchars($category['name']) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($category): ?>
        <!-- Category Header -->
        <div class="page-header fade-in-up">
            <div class="page-header-content">
                <h1><i class="fas fa-layer-group"></i> <?= htmlspecialchars($category['name']) ?></h1>
                <p>Découvrez nos séries de produits dans cette catégorie</p>
            </div>
        </div>

        <?php if (empty($series)): ?>
            <!-- Empty State -->
            <div class="empty-state scale-in">
                <i class="fas fa-box-open"></i>
                <h3>Aucune série disponible</h3>
                <p>Il n'y a pas encore de séries dans cette catégorie.<br>
                   Nous travaillons à enrichir notre catalogue pour vous offrir plus de choix.</p>
                <a href="profile.php" class="back-to-home">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
            </div>
        <?php else: ?>
            <!-- Series Grid -->
            <div class="series-grid">
                <?php foreach ($series as $index => $serie): ?>
                    <?php
                    // Get price range for this series
                    $stmt = $conn->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE series_id = ? AND category_id = ?");
                    $stmt->bind_param("ii", $serie['id'], $categoryId);
                    $stmt->execute();
                    $priceRange = $stmt->get_result()->fetch_assoc();
                    ?>
                    <div class="series-card fade-in-up" style="animation-delay: <?= $index * 0.15 ?>s;" onclick="navigateToSeries(<?= $serie['id'] ?>, <?= $categoryId ?>)">
                        <div class="series-image-container">
                            <img src="uploads/<?= htmlspecialchars($serie['image']) ?>" alt="<?= htmlspecialchars($serie['name']) ?>" class="series-image" onerror="this.src='https://via.placeholder.com/320x220/f0f0f0/999?text=<?= urlencode($serie['name']) ?>'">
                            <div class="series-overlay"></div>
                            <div class="series-badge">
                                <?= $serie['product_count'] ?> produit<?= $serie['product_count'] > 1 ? 's' : '' ?>
                            </div>
                        </div>
                        <div class="series-info">
                            <h3 class="series-title"><?= htmlspecialchars($serie['name']) ?></h3>
                            <p class="series-description">
                                Une gamme complète de produits d'éclairage de qualité supérieure.
                            </p>
                            <div class="series-stats">
                                <div class="series-count">
                                    <i class="fas fa-boxes"></i>
                                    <?= $serie['product_count'] ?> produit<?= $serie['product_count'] > 1 ? 's' : '' ?>
                                </div>
                                <?php if ($priceRange && $priceRange['min_price']): ?>
                                <div class="series-price-range">
                                    <?php if ($priceRange['min_price'] == $priceRange['max_price']): ?>
                                        <?= number_format($priceRange['min_price'], 2) ?> DT
                                    <?php else: ?>
                                        <?= number_format($priceRange['min_price'], 2) ?> - <?= number_format($priceRange['max_price'], 2) ?> DT
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="series.php?id=<?= $serie['id'] ?>&category=<?= $categoryId ?>" class="view-products-btn" onclick="event.stopPropagation();">
                                <i class="fas fa-eye"></i>
                                Voir les produits
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Category not found -->
        <div class="empty-state scale-in">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Catégorie introuvable</h3>
            <p>La catégorie que vous recherchez n'existe pas ou a été supprimée.</p>
            <a href="profile.php" class="back-to-home">
                <i class="fas fa-arrow-left"></i> Retour à l'accueil
            </a>
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

// Navigate to series with loading effect
function navigateToSeries(seriesId, categoryId) {
    showLoading();
    setTimeout(() => {
        window.location.href = `series.php?id=${seriesId}&category=${categoryId}`;
    }, 300);
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
    const cards = document.querySelectorAll('.series-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.series-title');
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
            card.style.pointerEvents = 'none';
        }
    });
    
    // Reset pointer events when search is cleared
    if (searchTerm === '') {
        cards.forEach(card => {
            card.style.pointerEvents = 'auto';
        });
    }
});

// Add hover sound effect and enhanced interactions
document.querySelectorAll('.series-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.filter = 'brightness(1.05)';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.filter = 'brightness(1)';
    });
    
    // Add ripple effect on click
    card.addEventListener('click', function(e) {
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
        // Don't hide images, only animate other elements
        if (!el.classList.contains('series-image')) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px) scale(0.95)';
        }
        observer.observe(el);
    });
    
    // Add staggered animation to series cards
    const seriesCards = document.querySelectorAll('.series-card');
    seriesCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        // Ensure images stay visible
        const img = card.querySelector('.series-image');
        if (img) {
            img.style.opacity = '1';
        }
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
        } else {
            window.history.back();
        }
    }
});

// Add subtle parallax effect to header
window.addEventListener('scroll', function() {
    const header = document.querySelector('.page-header');
    if (header) {
        const scrolled = window.pageYOffset;
        const parallax = scrolled * 0.5;
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

// Add notification system for better UX
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
    }, 3000);
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

// Log page view for analytics (optional)
console.log('Category page viewed:', '<?= $category ? htmlspecialchars($category['name']) : 'Unknown' ?>');
</script>
</body>
</html>