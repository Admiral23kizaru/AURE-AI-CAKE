<?php
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><title>Cake Shop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>:root{--theme-purple:#7b2cbf;--theme-light-purple:#e9d8fd}body{background:#faf8ff}.btn-primary{background:var(--theme-purple)!important;border-color:var(--theme-purple)!important}.btn-outline-primary{color:var(--theme-purple)!important;border-color:var(--theme-purple)!important}</style>
</head><body><div class="container-fluid"><nav class="navbar navbar-expand-lg navbar-light bg-white mb-3" style="box-shadow:0 1px 0 rgba(0,0,0,.06);"><div class="container-fluid">
<a class="navbar-brand d-flex align-items-center gap-2" href="../index.php" style="text-decoration:none;"><img src="../logo.png" alt="logo" style="height:32px;width:auto;"><span class="brand-text" style="color:var(--theme-purple); font-weight:700;">CAKE SHOP</span></a>
<div class="d-flex align-items-center"><a class="btn btn-outline-primary btn-sm me-2" href="../index.php">View Site</a><?php if(isset($_SESSION['user'])): ?><a class="btn btn-sm btn-primary" href="dashboard.php">Dashboard</a><?php endif; ?></div>
</div></nav></div><div class="container">
