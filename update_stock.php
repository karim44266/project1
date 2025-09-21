<?php
session_start();
require 'config.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=intval($_POST['id']);
  $stock=intval($_POST['stock']);
  $stmt=$conn->prepare("UPDATE products SET stock=? WHERE id=?");
  $stmt->bind_param("ii",$stock,$id);
  echo $stmt->execute() ? "Stock mis à jour" : "Erreur: ".$conn->error;
}
?>
