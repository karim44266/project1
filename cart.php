<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$cartIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$products = [];
$total = 0;

if (!empty($cartIds)) {
    // Fetch product details for all cart items
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $types = str_repeat('i', count($cartIds));
    $sql = "SELECT id, name, price, image FROM products WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$cartIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total
    foreach ($products as $product) {
        $total += $product['price'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Panier - EKOLED</title>
    <link rel="stylesheet" href="./assets/profile_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .container { padding: 30px; max-width: 1000px; margin: 0 auto; }
        .cart-item { 
            display: flex; 
            align-items: center; 
            padding: 15px; 
            border: 1px solid #ddd; 
            margin: 10px 0; 
            border-radius: 8px; 
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .cart-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 5px; margin-right: 15px; }
        .cart-item-info { flex-grow: 1; }
        .cart-item h4 { margin: 0 0 5px 0; color: #333; }
        .cart-item p { margin: 0; color: #666; }
        .remove-btn { 
            background: #ff4444; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 5px; 
            cursor: pointer;
            transition: background 0.3s;
        }
        .remove-btn:hover { background: #cc3333; }
        .cart-total { 
            text-align: right; 
            font-size: 1.5em; 
            font-weight: bold; 
            margin: 20px 0; 
            padding: 15px; 
            background: #f9f9f9; 
            border-radius: 8px;
            border: 2px solid #00ffcc;
        }
        .order-btn { 
            background: linear-gradient(45deg, #00ffcc, #00d4aa); 
            color: #1a1a1a; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 1.1em;
            font-weight: bold;
            transition: all 0.3s;
        }
        .order-btn:hover { 
            background: linear-gradient(45deg, #00d4aa, #00b894);
            transform: translateY(-2px);
        }
        .empty-cart { 
            text-align: center; 
            padding: 50px; 
            color: #666; 
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .continue-shopping {
            display: inline-block; 
            margin-top: 15px; 
            padding: 10px 20px; 
            background: #667eea; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px;
            transition: background 0.3s;
        }
        .continue-shopping:hover { background: #5a67d8; }
    </style>
</head>
<body>

<header>
    <div class="logo"><img src="uploads/ekoled2.png" width="100" height="50"></div>
    <nav>
        <a href="profile.php">Accueil</a>
        <a href="products.php">Produits</a>
        <a href="cart.php" class="active">Mon Panier</a>
        <a href="#">À propos</a>
    </nav>
    <div class="header-icons">
        <input type="text" class="search-bar" placeholder="Rechercher...">
        <a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
        <a href="#"><i class="fas fa-user"></i></a>
    </div>
</header>

<div class="container">
    <h1>Mon Panier</h1>
    
    <?php if($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart" style="font-size: 4em; color: #ddd; margin-bottom: 20px;"></i>
            <h3>Votre panier est vide</h3>
            <p>Ajoutez des produits à votre panier depuis la page produits</p>
            <a href="products.php" class="continue-shopping">Continuer mes achats</a>
        </div>
    <?php else: ?>
        <?php foreach ($products as $product): ?>
            <div class="cart-item">
                <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <div class="cart-item-info">
                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                    <p>Prix: <?= number_format($product['price'], 2) ?> DT</p>
                </div>
                <form method="POST" action="remove_from_list.php" style="margin: 0;">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <button type="submit" class="remove-btn">
                        <i class="fas fa-trash"></i> Retirer
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
        
        <div class="cart-total">
            <i class="fas fa-calculator"></i> Total: <?= number_format($total, 2) ?> DT
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="products.php" class="continue-shopping" style="margin-right: 15px;">
                <i class="fas fa-arrow-left"></i> Continuer mes achats
            </a>
            <form method="POST" action="place_order.php" style="display: inline;">
                <button type="submit" class="order-btn">
                    <a href="checkout.php" class="order-btn">
    <i class="fas fa-check"></i> Passer la commande
</a>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

</body>
</html>