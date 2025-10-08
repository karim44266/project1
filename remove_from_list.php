<?php
// remove_from_list.php
session_start();


// require login (optional)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ensure cart exists
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

if (isset($_POST['product_id'])) {
    $pid = intval($_POST['product_id']);

    // find and remove
    $key = array_search($pid, $_SESSION['cart']);
    if ($key !== false) {
        unset($_SESSION['cart'][$key]);

        // Re-index to avoid holes (important when using implode later)
        $_SESSION['cart'] = array_values($_SESSION['cart']);

        $_SESSION['success'] = "Produit retiré de la liste.";
    } else {
        $_SESSION['error'] = "Produit non trouvé dans la liste.";
    }
}


header("Location: cart.php");
exit();
