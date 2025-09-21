<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Commande réussie</title>
  <style>
  body { font-family:Arial, sans-serif; text-align:center; margin-top:100px; }
  .success { font-size:24px; color:green; }
  a { display:inline-block; margin-top:20px; text-decoration:none; padding:10px 18px; background:#28a745; color:#fff; border-radius:6px; }
  </style>
</head>
<body>
  <p class="success">✅ Votre commande a été enregistrée avec succès.</p>
  <a href="products.php">Retourner aux produits</a>
</body>
</html>
