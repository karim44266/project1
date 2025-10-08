<?php
session_start();
require 'config.php';
if (!isset($_SESSION['user_id'])) { echo "Vous devez être connecté."; exit(); }

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if (isset($_POST['product_id'])) {
  $pid = intval($_POST['product_id']);
  if (!in_array($pid, $_SESSION['cart'], true)) {
    $_SESSION['cart'][] = $pid;
    echo "Produit ajouté à la liste !";
  } else {
    echo "Produit déjà dans la liste.";
  }
} else {
  echo "Produit non spécifié.";
}
