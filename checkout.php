<?php
// checkout.php - Page de paiement
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$cartIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (empty($cartIds)) {
    header("Location: cart.php");
    exit();
}

// Calculer le total
$total = 0;
$products = [];

if (!empty($cartIds)) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $types = str_repeat('i', count($cartIds));
    $sql = "SELECT id, name, price, image FROM products WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$cartIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    
    foreach ($products as $product) {
        $total += $product['price'];
    }
}

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'];
    $customer_name = $_POST['customer_name'];
    $customer_phone = $_POST['customer_phone'];
    $customer_address = $_POST['customer_address'];
    
    // Créer la commande
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, payment_method, customer_name, customer_phone, customer_address, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("idssss", $user_id, $total, $payment_method, $customer_name, $customer_phone, $customer_address);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();
    
    // Ajouter les items
    $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, 1, ?)");
    foreach ($products as $product) {
        $insertItem->bind_param("iid", $order_id, $product['id'], $product['price']);
        $insertItem->execute();
    }
    $insertItem->close();
    
    // Vider le panier
    unset($_SESSION['cart']);
    
    // Rediriger selon la méthode de paiement
    if ($payment_method === 'card') {
        header("Location: payment_card.php?order_id=" . $order_id);
    } elseif ($payment_method === 'paypal') {
        header("Location: payment_paypal.php?order_id=" . $order_id);
    } else {
        // Paiement à la livraison
        $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();
        header("Location: order_success.php?order_id=" . $order_id);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement - EKOLED</title>
    <link rel="stylesheet" href="./assets/profile_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .checkout-form, .order-summary {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #00ffcc;
            outline: none;
        }
        
        .payment-methods {
            display: grid;
            gap: 15px;
            margin: 20px 0;
        }
        
        .payment-option {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .payment-option:hover {
            border-color: #00ffcc;
            background: #f0fff4;
        }
        
        .payment-option.selected {
            border-color: #00ffcc;
            background: #e6fffa;
        }
        
        .payment-option input[type="radio"] {
            width: auto;
        }
        
        .payment-icon {
            font-size: 24px;
            width: 40px;
            text-align: center;
        }
        
        .payment-details {
            flex-grow: 1;
        }
        
        .payment-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .payment-desc {
            color: #666;
            font-size: 12px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }
        
        .item-info {
            flex-grow: 1;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .item-price {
            color: #00ffcc;
            font-weight: bold;
        }
        
        .total-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #00ffcc;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        
        .total-final {
            font-size: 18px;
            font-weight: bold;
            color: #00ffcc;
        }
        
        .pay-button {
            width: 100%;
            background: linear-gradient(45deg, #00ffcc, #00d4aa);
            color: #1a1a1a;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .pay-button:hover {
            background: linear-gradient(45deg, #00d4aa, #00b894);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="logo"><img src="uploads/ekoled2.png" width="100" height="50"></div>
    <nav>
        <a href="profile.php">Accueil</a>
        <a href="products.php">Produits</a>
        <a href="cart.php">Mon Panier</a>
        <a href="#">À propos</a>
    </nav>
</header>

<div class="checkout-container">
    <div class="checkout-form">
        <h2><i class="fas fa-credit-card"></i> Finaliser votre commande</h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Nom complet *</label>
                <input type="text" name="customer_name" required>
            </div>
            
            <div class="form-group">
                <label>Téléphone *</label>
                <input type="number" name="customer_phone" required>
            </div>
            
            <div class="form-group">
                <label>Adresse de livraison *</label>
                <textarea name="customer_address" rows="3" required></textarea>
            </div>
            
            <h3>Méthode de paiement</h3>
            <div class="payment-methods">
                <div class="payment-option" onclick="selectPayment('cod', this)">
                    <input type="radio" name="payment_method" value="cod" id="cod" checked>
                    <div class="payment-icon" style="color: #28a745;">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="payment-details">
                        <div class="payment-title">Paiement à la livraison</div>
                        <div class="payment-desc">Payez en espèces lors de la réception</div>
                    </div>
                </div>
                
                <div class="payment-option" onclick="selectPayment('card', this)">
                    <input type="radio" name="payment_method" value="card" id="card">
                    <div class="payment-icon" style="color: #007bff;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="payment-details">
                        <div class="payment-title">Carte bancaire</div>
                        <div class="payment-desc">Visa, Mastercard, etc.</div>
                    </div>
                </div>
                
                <div class="payment-option" onclick="selectPayment('paypal', this)">
                    <input type="radio" name="payment_method" value="paypal" id="paypal">
                    <div class="payment-icon" style="color: #0070ba;">
                        <i class="fab fa-paypal"></i>
                    </div>
                    <div class="payment-details">
                        <div class="payment-title">PayPal</div>
                        <div class="payment-desc">Paiement sécurisé via PayPal</div>
                    </div>
                </div>
                
                <div class="payment-option" onclick="selectPayment('bank', this)">
                    <input type="radio" name="payment_method" value="bank" id="bank">
                    <div class="payment-icon" style="color: #6c757d;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="payment-details">
                        <div class="payment-title">Virement bancaire</div>
                        <div class="payment-desc">Paiement par virement</div>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="process_payment" class="pay-button">
                <i class="fas fa-lock"></i> Finaliser le paiement
            </button>
        </form>
    </div>
    
    <div class="order-summary">
        <h3><i class="fas fa-receipt"></i> Récapitulatif</h3>
        
        <?php foreach ($products as $product): ?>
            <div class="order-item">
                <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <div class="item-info">
                    <div class="item-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="item-price"><?= number_format($product['price'], 2) ?> DT</div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="total-section">
            <div class="total-line">
                <span>Sous-total:</span>
                <span><?= number_format($total, 2) ?> DT</span>
            </div>
            <div class="total-line">
                <span>Livraison:</span>
                <span>Gratuite</span>
            </div>
            <div class="total-line total-final">
                <span>Total:</span>
                <span><?= number_format($total, 2) ?> DT</span>
            </div>
        </div>
    </div>
</div>

<script>
function selectPayment(method, element) {
    // Retirer la sélection précédente
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Ajouter la sélection
    element.classList.add('selected');
    document.getElementById(method).checked = true;
}

// Sélectionner COD par défaut
document.addEventListener('DOMContentLoaded', function() {
    selectPayment('cod', document.querySelector('.payment-option'));
});
</script>

</body>
</html>