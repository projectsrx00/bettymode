<?php
// Inclusion de la connexion à la base de données
include('db.php');

// ============================================================
// TRAITEMENT DES REQUÊTES AJAX
// ============================================================

// Vérifier si c'est une requête AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // === TRAITEMENT DES COMMANDES ===
        if (isset($_POST['action_commande'])) {
            $action = $_POST['action_commande'];
            $commandeId = isset($_POST['commande_id']) ? intval($_POST['commande_id']) : 0;
            
            if ($action === 'update' && isset($_POST['statut'])) {
                // Mettre à jour le statut d'une commande
                $statut = $_POST['statut'];
                $allowedStatuts = ['en-attente', 'confirmee', 'expediee', 'livree', 'annulee'];
                
                if (!in_array($statut, $allowedStatuts)) {
                    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
                    exit;
                }
                
                $stmt = $pdo->prepare("UPDATE commandes SET statut = ?, date_modification = NOW() WHERE id = ?");
                $stmt->execute([$statut, $commandeId]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Commande non trouvée ou déjà à jour']);
                }
                exit;
            }
            
            if ($action === 'delete') {
                // Supprimer une commande (les articles seront supprimés par ON DELETE CASCADE)
                $stmt = $pdo->prepare("DELETE FROM commandes WHERE id = ?");
                $stmt->execute([$commandeId]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Commande supprimée']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
                }
                exit;
            }
        }
        
        // === TRAITEMENT DES PRODUITS ===
        if (isset($_POST['action_produit'])) {
            $action = $_POST['action_produit'];
            
            if ($action === 'delete') {
                $produitId = isset($_POST['produit_id']) ? intval($_POST['produit_id']) : 0;
                
                if ($produitId > 0) {
                    // Supprimer le produit (les relations seront supprimées par ON DELETE CASCADE)
                    $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ?");
                    $stmt->execute([$produitId]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Produit supprimé']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
                }
                exit;
            }
            
            if ($action === 'save') {
                // Ajouter ou modifier un produit
                $id = isset($_POST['produit_id']) && !empty($_POST['produit_id']) ? intval($_POST['produit_id']) : null;
                $nom = trim($_POST['nom'] ?? '');
                $prix = floatval($_POST['prix'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                $stock = intval($_POST['stock'] ?? 0);
                $image = trim($_POST['image'] ?? '');
                $actif = isset($_POST['actif']) ? intval($_POST['actif']) : 1;
                $tailles = isset($_POST['tailles']) ? array_map('intval', $_POST['tailles']) : [];
                $couleurs = isset($_POST['couleurs']) ? array_map('intval', $_POST['couleurs']) : [];
                
                // Traitement des images supplémentaires
                $imagesExtra = [];
                if (isset($_POST['images_extra']) && !empty($_POST['images_extra'])) {
                    $imagesExtraText = trim($_POST['images_extra']);
                    // Séparer par ligne
                    $lines = explode("\n", $imagesExtraText);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (!empty($line)) {
                            $imagesExtra[] = $line;
                        }
                    }
                }
                $imagesExtraJson = !empty($imagesExtra) ? json_encode($imagesExtra) : null;
                
                if (empty($nom) || $prix <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Nom et prix sont requis']);
                    exit;
                }
                
                $pdo->beginTransaction();
                
                if ($id) {
                    // Modifier un produit existant
                    $stmt = $pdo->prepare("
                        UPDATE produits 
                        SET nom = ?, prix = ?, description = ?, stock = ?, image = ?, 
                            images_extra = ?, actif = ?, date_modification = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$nom, $prix, $description, $stock, $image, $imagesExtraJson, $actif, $id]);
                    
                    // Supprimer les anciennes relations
                    $pdo->prepare("DELETE FROM produit_tailles WHERE produit_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM produit_couleurs WHERE produit_id = ?")->execute([$id]);
                } else {
                    // Ajouter un nouveau produit
                    $stmt = $pdo->prepare("
                        INSERT INTO produits (nom, prix, description, stock, image, images_extra, actif, date_creation, date_modification)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$nom, $prix, $description, $stock, $image, $imagesExtraJson, $actif]);
                    $id = $pdo->lastInsertId();
                }
                
                // Ajouter les tailles
                if (!empty($tailles)) {
                    $stmt = $pdo->prepare("INSERT INTO produit_tailles (produit_id, taille_id) VALUES (?, ?)");
                    foreach ($tailles as $tailleId) {
                        $stmt->execute([$id, $tailleId]);
                    }
                }
                
                // Ajouter les couleurs
                if (!empty($couleurs)) {
                    $stmt = $pdo->prepare("INSERT INTO produit_couleurs (produit_id, couleur_id) VALUES (?, ?)");
                    foreach ($couleurs as $couleurId) {
                        $stmt->execute([$id, $couleurId]);
                    }
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Produit sauvegardé avec succès', 'id' => $id]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
// ============================================================

// Récupérer toutes les commandes avec leurs détails
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM commande_articles WHERE commande_id = c.id) as nb_articles
    FROM commandes c 
    ORDER BY c.date_commande DESC
");
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les articles pour chaque commande
$articlesParCommande = [];
foreach ($commandes as $commande) {
    $stmt = $pdo->prepare("
        SELECT ca.*, p.nom as produit_nom 
        FROM commande_articles ca 
        JOIN produits p ON ca.produit_id = p.id 
        WHERE ca.commande_id = ?
    ");
    $stmt->execute([$commande['id']]);
    $articlesParCommande[$commande['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer tous les produits
$stmt = $pdo->prepare("SELECT * FROM produits WHERE actif = 1 ORDER BY id");
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les couleurs pour le formulaire
$stmt = $pdo->prepare("SELECT * FROM couleurs ORDER BY nom");
$stmt->execute();
$couleursDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les tailles pour le formulaire
$stmt = $pdo->prepare("SELECT * FROM tailles ORDER BY 
    CASE 
        WHEN valeur = 'S' THEN 1
        WHEN valeur = 'M' THEN 2
        WHEN valeur = 'L' THEN 3
        WHEN valeur = 'XL' THEN 4
        WHEN valeur REGEXP '^[0-9]+$' THEN CAST(valeur AS UNSIGNED) + 10
        ELSE 99
    END
");
$stmt->execute();
$taillesDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalCommandes = count($commandes);
$commandesEnAttente = count(array_filter($commandes, function($c) { return $c['statut'] === 'en-attente'; }));
$commandesConfirmees = count(array_filter($commandes, function($c) { return $c['statut'] === 'confirmee'; }));
$commandesExpediees = count(array_filter($commandes, function($c) { return $c['statut'] === 'expediee'; }));
$commandesLivrees = count(array_filter($commandes, function($c) { return $c['statut'] === 'livree'; }));
$commandesAnnulees = count(array_filter($commandes, function($c) { return $c['statut'] === 'annulee'; }));

// Revenu total (commandes non annulées)
$revenuTotal = array_reduce($commandes, function($sum, $c) {
    return $sum + ($c['statut'] !== 'annulee' ? floatval($c['total']) : 0);
}, 0);

// Clients uniques
$clientsUniques = [];
foreach ($commandes as $c) {
    $clientsUniques[$c['telephone']] = [
        'nom' => $c['nom_client'],
        'telephone' => $c['telephone'],
        'email' => $c['email'] ?? 'N/A',
        'ville' => $c['ville']
    ];
}
$nbClients = count($clientsUniques);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  :root {
    --sidebar-width: 260px;
  }

  .admin-layout {
    display: flex;
    min-height: 100vh;
  }

  /* Sidebar */
  .sidebar {
    width: var(--sidebar-width);
    background: var(--carte);
    border-right: 1px solid var(--bordure);
    padding: 24px 0;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    transition: transform 0.3s;
  }

  .sidebar .logo {
    padding: 0 24px 24px;
    border-bottom: 1px solid var(--bordure);
    margin-bottom: 24px;
    display: block;
    text-decoration: none;
  }

  .sidebar .logo h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    margin: 0;
    color: var(--blanc);
  }
  .sidebar .logo h2 span { color: var(--rose); }
  .sidebar .logo p { color: var(--gris); font-size: 12px; margin: 4px 0 0; }

  .nav-admin { list-style: none; padding: 0; margin: 0; }
  .nav-admin li { margin-bottom: 4px; }
  .nav-admin li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    color: var(--gris);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    cursor: pointer;
  }
  .nav-admin li a:hover,
  .nav-admin li a.active {
    color: var(--blanc);
    background: rgba(203, 161, 53, 0.05);
    border-left-color: var(--or);
  }
  .nav-admin li a .icon { font-size: 18px; width: 24px; text-align: center; }
  .nav-admin .badge-nav {
    background: var(--rose);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: auto;
  }

  /* Main Content */
  .main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    padding: 30px;
    background: var(--noir);
    min-height: 100vh;
  }

  .top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 16px;
  }
  .top-bar h1 { font-family: 'Playfair Display', serif; font-size: 28px; margin: 0; }
  .top-bar .admin-info {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .top-bar .admin-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--or);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--blanc);
    font-weight: 600;
  }
  .btn-deconnexion {
    padding: 8px 16px;
    background: transparent;
    border: 1px solid var(--bordure);
    color: var(--gris);
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    transition: all 0.2s;
  }
  .btn-deconnexion:hover { border-color: var(--rose); color: var(--rose); }

  /* Stats Cards */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }
  .stat-card {
    background: var(--carte);
    border: 1px solid var(--bordure);
    border-radius: 14px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
  }
  .stat-card .stat-icon.commandes { background: rgba(203, 161, 53, 0.15); }
  .stat-card .stat-icon.revenue { background: rgba(76, 175, 80, 0.15); }
  .stat-card .stat-icon.clients { background: rgba(33, 150, 243, 0.15); }
  .stat-card .stat-icon.en-attente { background: rgba(255, 152, 0, 0.15); }
  .stat-card .stat-info h3 { font-size: 24px; margin: 0 0 4px; font-weight: 600; }
  .stat-card .stat-info p { color: var(--gris); font-size: 13px; margin: 0; }

  /* Table */
  .table-container {
    background: var(--carte);
    border: 1px solid var(--bordure);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 30px;
  }
  .table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--bordure);
    flex-wrap: wrap;
    gap: 12px;
  }
  .table-header h3 { margin: 0; font-family: 'Playfair Display', serif; font-size: 20px; }
  .table-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .btn-filtre {
    padding: 8px 14px;
    background: transparent;
    border: 1px solid var(--bordure);
    color: var(--gris);
    border-radius: 20px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
    font-family: 'Poppins', sans-serif;
  }
  .btn-filtre:hover,
  .btn-filtre.active {
    background: var(--or);
    color: var(--blanc);
    border-color: var(--or);
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }
  thead th {
    text-align: left;
    padding: 14px 24px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--gris);
    border-bottom: 1px solid var(--bordure);
    background: rgba(255, 255, 255, 0.02);
  }
  tbody td {
    padding: 16px 24px;
    font-size: 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }
  tbody tr:hover { background: rgba(203, 161, 53, 0.03); }
  tbody tr:last-child td { border-bottom: none; }

  .status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
  }
  .status.en-attente { background: rgba(255, 152, 0, 0.15); color: #FFA726; }
  .status.confirmee { background: rgba(33, 150, 243, 0.15); color: #42A5F5; }
  .status.expediee { background: rgba(156, 39, 176, 0.15); color: #AB47BC; }
  .status.livree { background: rgba(76, 175, 80, 0.15); color: #66BB6A; }
  .status.annulee { background: rgba(244, 67, 54, 0.15); color: #EF5350; }

  .btn-action {
    padding: 6px 12px;
    border: 1px solid var(--bordure);
    background: transparent;
    color: var(--blanc);
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    margin-right: 4px;
    transition: all 0.2s;
    font-family: 'Poppins', sans-serif;
  }
  .btn-action:hover { border-color: var(--or); color: var(--or); }
  .btn-action.voir { color: var(--or); border-color: var(--or); }
  .btn-action.supprimer { color: #EF5350; border-color: #EF5350; }
  .btn-action.supprimer:hover { background: rgba(244, 67, 54, 0.1); }

  .status-select {
    padding: 6px 12px;
    background: var(--noir-clair);
    border: 1px solid var(--bordure);
    color: var(--blanc);
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
  }

  /* Form section */
  .form-section {
    background: var(--carte);
    border: 1px solid var(--bordure);
    border-radius: 14px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    margin-top: 25px;
  }
  .form-section h3 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    margin: 0 0 25px 0;
    color: var(--blanc);
    border-bottom: 1px solid var(--bordure);
    padding-bottom: 15px;
  }
  .form-row {
    display: flex;
    gap: 24px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }
  .form-group {
    flex: 1;
    min-width: 250px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .form-group label {
    font-size: 13px;
    font-weight: 500;
    color: var(--gris);
    letter-spacing: 0.5px;
  }
  .form-group input, 
  .form-group textarea, 
  .form-group select {
    background: var(--noir);
    border: 1px solid var(--bordure);
    color: var(--blanc);
    padding: 12px 16px;
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    outline: none;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
  }
  .form-group input:focus, 
  .form-group textarea:focus,
  .form-group select:focus {
    border-color: var(--or);
    box-shadow: 0 0 0 2px rgba(203, 161, 53, 0.15);
  }
  .form-group textarea {
    height: 110px;
    resize: vertical;
  }
  .form-group select[multiple] {
    height: 80px;
  }
  .form-group .help-text {
    font-size: 11px;
    color: var(--gris);
    margin-top: 4px;
  }

  /* Modal */
  .modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }
  .modal-overlay.active { display: flex; }
  .modal {
    background: var(--carte);
    border: 1px solid var(--bordure);
    border-radius: 14px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
  }
  .modal h3 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    margin: 0 0 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--bordure);
  }
  .modal .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 14px;
  }
  .modal .detail-row .label { color: var(--gris); }
  .modal .produits-commande {
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    padding: 16px;
  }
  .modal .produit-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 13px;
  }
  .modal .btn-close {
    display: block;
    width: 100%;
    margin-top: 20px;
    padding: 12px;
    background: var(--or);
    color: var(--blanc);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
  }

  .section-hidden { display: none; }
  .section-visible { display: block; }

  .btn-or {
    background: var(--or);
    color: var(--blanc);
    padding: 8px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s;
    font-size: 14px;
  }
  .btn-or:hover { background: #e6c200; transform: translateY(-2px); }
  .btn-outline {
    background: transparent;
    border: 1px solid var(--bordure);
    color: var(--blanc);
    padding: 8px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s;
    font-size: 14px;
  }
  .btn-outline:hover { border-color: var(--or); color: var(--or); }

  .btn-sm { font-size: 13px; padding: 6px 14px; }

  .toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--carte);
    border: 1px solid var(--rose);
    color: var(--blanc);
    padding: 14px 22px;
    border-radius: 10px;
    font-size: 14px;
    opacity: 0;
    transform: translateY(10px);
    transition: .25s;
    pointer-events: none;
    z-index: 1000;
  }
  .toast.show { opacity: 1; transform: translateY(0); }
  .toast.success { border-color: #4CAF50; }
  .toast.error { border-color: #e07b73; }

  .mobile-menu-btn {
    display: none;
    background: transparent;
    border: none;
    color: var(--blanc);
    font-size: 24px;
    cursor: pointer;
  }

  @media (max-width: 768px) {
    .sidebar {
      transform: translateX(-100%);
    }
    .sidebar.open {
      transform: translateX(0);
    }
    .main-content {
      margin-left: 0;
    }
    .mobile-menu-btn {
      display: block !important;
    }
    table {
      font-size: 12px;
    }
    thead th, tbody td {
      padding: 10px 12px;
    }
    .stats-grid {
      grid-template-columns: 1fr 1fr;
    }
    .form-row {
      flex-direction: column;
    }
    .form-group {
      min-width: 100%;
    }
  }
</style>
</head>
<body>

<div class="admin-layout">
  
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <a href="admin.php" class="logo">
      <h2>Betty<span>_</span>Mode</h2>
      <p>Dashboard Administrateur</p>
    </a>
    <ul class="nav-admin">
      <li><a href="#" class="active" onclick="afficherSection('commandes')"><span class="icon">📋</span> Commandes <span class="badge-nav" id="badgeEnAttente"><?= $commandesEnAttente ?></span></a></li>
      <li><a href="#" onclick="afficherSection('produits')"><span class="icon">👗</span> Produits</a></li>
      <li><a href="#" onclick="afficherSection('clients')"><span class="icon">👥</span> Clients</a></li>
      <li><a href="#" onclick="afficherSection('stats')"><span class="icon">📊</span> Statistiques</a></li>
      <li><a href="index.php" onclick="return confirm('Déconnexion ?')"><span class="icon">🚪</span> Déconnexion</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
    
    <div class="top-bar">
      <h1>Dashboard</h1>
      <div class="admin-info">
        <span style="color:var(--gris); font-size:14px;" id="adminNom">Admin</span>
        <div class="admin-avatar" id="adminInitial">A</div>
        <button class="btn-deconnexion" onclick="if(confirm('Déconnexion ?')) window.location.href='index.php'">Déconnexion</button>
      </div>
    </div>

    <!-- Section Statistiques (visible par défaut sur l'accueil) -->
    <div id="section-stats" class="section-visible">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon commandes">📋</div>
          <div class="stat-info">
            <h3><?= $totalCommandes ?></h3>
            <p>Total commandes</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon en-attente">⏳</div>
          <div class="stat-info">
            <h3><?= $commandesEnAttente ?></h3>
            <p>En attente</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon revenue">💰</div>
          <div class="stat-info">
            <h3><?= number_format($revenuTotal, 2, ',', ' ') ?> DH</h3>
            <p>Revenu total</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon clients">👥</div>
          <div class="stat-info">
            <h3><?= $nbClients ?></h3>
            <p>Clients</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Section Commandes -->
    <div id="section-commandes" class="section-hidden">
      <div class="table-container">
        <div class="table-header">
          <h3>📋 Gestion des commandes</h3>
          <div class="table-actions">
            <button class="btn-filtre active" onclick="filtrerCommandes('toutes', this)">Toutes</button>
            <button class="btn-filtre" onclick="filtrerCommandes('en-attente', this)">En attente</button>
            <button class="btn-filtre" onclick="filtrerCommandes('confirmee', this)">Confirmées</button>
            <button class="btn-filtre" onclick="filtrerCommandes('expediee', this)">Expédiées</button>
            <button class="btn-filtre" onclick="filtrerCommandes('livree', this)">Livrées</button>
            <button class="btn-filtre" onclick="filtrerCommandes('annulee', this)">Annulées</button>
          </div>
        </div>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>N° Commande</th>
                <th>Client</th>
                <th>Téléphone</th>
                <th>Ville</th>
                <th>Total</th>
                <th>Date</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="tableCommandes">
              <?php if (empty($commandes)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--gris); padding:30px;">Aucune commande</td></tr>
              <?php else: ?>
                <?php foreach ($commandes as $c): ?>
                  <tr data-statut="<?= $c['statut'] ?>" data-id="<?= $c['id'] ?>">
                    <td><strong>#<?= htmlspecialchars($c['numero']) ?></strong></td>
                    <td><?= htmlspecialchars($c['nom_client']) ?></td>
                    <td><?= htmlspecialchars($c['telephone']) ?></td>
                    <td><?= htmlspecialchars($c['ville']) ?></td>
                    <td><strong><?= number_format($c['total'], 2, ',', ' ') ?> DH</strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></td>
                    <td>
                      <select class="status-select" onchange="changerStatut(<?= $c['id'] ?>, this.value)">
                        <option value="en-attente" <?= $c['statut'] === 'en-attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="confirmee" <?= $c['statut'] === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                        <option value="expediee" <?= $c['statut'] === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                        <option value="livree" <?= $c['statut'] === 'livree' ? 'selected' : '' ?>>Livrée</option>
                        <option value="annulee" <?= $c['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                      </select>
                    </td>
                    <td>
                      <button class="btn-action voir" onclick="voirDetail(<?= $c['id'] ?>)">👁 Voir</button>
                      <button class="btn-action supprimer" onclick="supprimerCommande(<?= $c['id'] ?>)">🗑</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Section Produits -->
    <div id="section-produits" class="section-hidden">
      <div class="table-container">
        <div class="table-header">
          <h3>👗 Gestion des produits</h3>
          <button class="btn-or btn-sm" onclick="ajouterProduit()">+ Ajouter un produit</button>
        </div>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Nom</th>
                <th>Prix</th>
                <th>Stock</th>
                <th>Photos</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="tableProduits">
              <?php if (empty($produits)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--gris); padding:30px;">Aucun produit</td></tr>
              <?php else: ?>
                <?php foreach ($produits as $p): 
                  $nbPhotos = 1; // Image principale
                  if (!empty($p['images_extra'])) {
                      $extra = json_decode($p['images_extra'], true);
                      if (is_array($extra)) {
                          $nbPhotos += count($extra);
                      }
                  }
                ?>
                  <tr>
                    <td>#<?= $p['id'] ?></td>
                    <td><?= $p['image'] ? '🖼' : '—' ?></td>
                    <td><?= htmlspecialchars($p['nom']) ?></td>
                    <td><strong><?= number_format($p['prix'], 2, ',', ' ') ?> DH</strong></td>
                    <td><?= $p['stock'] ?></td>
                    <td><?= $nbPhotos ?> 📸</td>
                    <td>
                      <button class="btn-action voir" onclick="editerProduit(<?= $p['id'] ?>)">✏️ Modifier</button>
                      <button class="btn-action supprimer" onclick="supprimerProduit(<?= $p['id'] ?>)">🗑</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Formulaire ajout/modification produit -->
      <div class="form-section" id="formProduit" style="display:none;">
        <h3 id="titreFormProduit">Ajouter un produit</h3>
        <form method="POST" action="admin.php" onsubmit="return sauvegarderProduit(event)">
          <input type="hidden" name="action_produit" value="save">
          <input type="hidden" name="produit_id" id="editProduitId" value="">
          
          <div class="form-row">
            <div class="form-group">
              <label>Nom du produit *</label>
              <input type="text" name="nom" id="nomProduit" placeholder="Nom du produit" required>
            </div>
            <div class="form-group">
              <label>Prix (DH) *</label>
              <input type="number" name="prix" id="prixProduit" placeholder="0.00" step="0.01" required>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Description</label>
              <textarea name="description" id="descProduit" placeholder="Description du produit..."></textarea>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Tailles</label>
              <select name="tailles[]" id="taillesProduit" multiple>
                <?php foreach ($taillesDisponibles as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['valeur']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="help-text">Ctrl+clic pour sélectionner plusieurs</div>
            </div>
            <div class="form-group">
              <label>Couleurs</label>
              <select name="couleurs[]" id="couleursProduit" multiple>
                <?php foreach ($couleursDisponibles as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="help-text">Ctrl+clic pour sélectionner plusieurs</div>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Stock</label>
              <input type="number" name="stock" id="stockProduit" value="10" min="0">
            </div>
            <div class="form-group">
              <label>Image principale</label>
              <input type="text" name="image" id="imageProduit" placeholder="ex: images/produit.jpg">
              <div class="help-text">Chemin de l'image principale</div>
            </div>
            <div class="form-group">
              <label>Actif</label>
              <select name="actif" id="actifProduit">
                <option value="1">Oui</option>
                <option value="0">Non</option>
              </select>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Photos supplémentaires (une par ligne)</label>
              <textarea name="images_extra" id="imagesExtraProduit" rows="4" placeholder="images/photo2.jpg&#10;images/photo3.jpg&#10;images/photo4.jpg"></textarea>
              <div class="help-text">Chaque chemin d'image sur une nouvelle ligne. Ces photos apparaîtront en carrousel.</div>
            </div>
          </div>
          
          <div style="display:flex; gap:12px; margin-top:12px;">
            <button type="submit" class="btn-or">💾 Sauvegarder</button>
            <button type="button" class="btn-outline" onclick="annulerProduit()">Annuler</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Section Clients -->
    <div id="section-clients" class="section-hidden">
      <div class="table-container">
        <div class="table-header">
          <h3>👥 Liste des clients</h3>
        </div>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Ville</th>
                <th>Commandes</th>
              </tr>
            </thead>
            <tbody id="tableClients">
              <?php if (empty($clientsUniques)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--gris); padding:30px;">Aucun client</td></tr>
              <?php else: ?>
                <?php 
                $i = 0;
                foreach ($clientsUniques as $tel => $client): 
                  $nbCmd = count(array_filter($commandes, function($c) use ($tel) { return $c['telephone'] === $tel; }));
                  $i++;
                ?>
                  <tr>
                    <td>#<?= $i ?></td>
                    <td><?= htmlspecialchars($client['nom']) ?></td>
                    <td><?= htmlspecialchars($tel) ?></td>
                    <td><?= htmlspecialchars($client['email']) ?></td>
                    <td><?= htmlspecialchars($client['ville']) ?></td>
                    <td><?= $nbCmd ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- Modal Détail Commande -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modalDetail">
    <!-- Rempli dynamiquement par JavaScript -->
  </div>
</div>

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<script>
  // ============================================================
  // DONNÉES PHP PASSÉES EN JAVASCRIPT
  // ============================================================
  const commandesData = <?= json_encode($commandes) ?>;
  const articlesData = <?= json_encode($articlesParCommande) ?>;

  // ============================================================
  // NAVIGATION
  // ============================================================
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
  }

  function afficherSection(section) {
    // Cacher toutes les sections
    document.querySelectorAll('[id^="section-"]').forEach(el => {
      el.classList.add('section-hidden');
      el.classList.remove('section-visible');
    });
    
    // Afficher la section demandée
    const sectionEl = document.getElementById('section-' + section);
    if (sectionEl) {
      sectionEl.classList.remove('section-hidden');
      sectionEl.classList.add('section-visible');
    }

    // Mettre à jour la navigation
    document.querySelectorAll('.nav-admin a').forEach(a => a.classList.remove('active'));
    const liens = document.querySelectorAll('.nav-admin a');
    liens.forEach(l => {
      if (l.textContent.toLowerCase().includes(section)) {
        l.classList.add('active');
      }
    });

    // Fermer sidebar sur mobile
    document.getElementById('sidebar').classList.remove('open');
  }

  // ============================================================
  // TOAST NOTIFICATION
  // ============================================================
  function afficherToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  // ============================================================
  // GESTION DES COMMANDES
  // ============================================================
  function filtrerCommandes(filtre, btn) {
    document.querySelectorAll('.btn-filtre').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const rows = document.querySelectorAll('#tableCommandes tr');
    rows.forEach(row => {
      if (row.dataset.statut) {
        if (filtre === 'toutes' || row.dataset.statut === filtre) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      }
    });
  }

  function changerStatut(id, nouveauStatut) {
    fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action_commande=update&commande_id=' + id + '&statut=' + nouveauStatut
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        afficherToast('Statut mis à jour', 'success');
        // Mettre à jour l'affichage
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
          row.dataset.statut = nouveauStatut;
        }
      } else {
        afficherToast('Erreur : ' + data.message, 'error');
      }
    })
    .catch(() => afficherToast('Erreur lors de la mise à jour', 'error'));
  }

  function supprimerCommande(id) {
    if (!confirm('Supprimer définitivement cette commande ?')) return;
    
    fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action_commande=delete&commande_id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        afficherToast('Commande supprimée', 'success');
        location.reload();
      } else {
        afficherToast('Erreur : ' + data.message, 'error');
      }
    })
    .catch(() => afficherToast('Erreur lors de la suppression', 'error'));
  }

  function voirDetail(id) {
    const commande = commandesData.find(c => c.id === id);
    if (!commande) {
      afficherToast('Commande non trouvée', 'error');
      return;
    }

    const articles = articlesData[id] || [];
    
    const modal = document.getElementById('modalDetail');
    modal.innerHTML = `
      <h3>📋 Commande #${commande.numero}</h3>
      <div class="detail-row"><span class="label">Client</span><span>${commande.nom_client}</span></div>
      <div class="detail-row"><span class="label">Téléphone</span><span>${commande.telephone}</span></div>
      <div class="detail-row"><span class="label">Email</span><span>${commande.email || 'N/A'}</span></div>
      <div class="detail-row"><span class="label">Ville</span><span>${commande.ville}</span></div>
      <div class="detail-row"><span class="label">Quartier</span><span>${commande.quartier}</span></div>
      <div class="detail-row"><span class="label">Adresse</span><span>${commande.adresse}</span></div>
      <div class="detail-row"><span class="label">Date</span><span>${new Date(commande.date_commande).toLocaleString('fr-FR')}</span></div>
      <div class="detail-row"><span class="label">Statut</span><span class="status ${commande.statut}">${commande.statut}</span></div>
      <div class="detail-row"><span class="label">Paiement</span><span>${commande.mode_paiement || 'À la livraison'}</span></div>
      
      <div class="produits-commande">
        <h4 style="margin:0 0 12px;">Produits commandés</h4>
        ${articles.length > 0 ? articles.map((a, i) => `
          <div class="produit-item">
            <span>${i+1}. ${a.produit_nom} - ${a.taille || 'N/A'} ${a.couleur ? '(' + a.couleur + ')' : ''} ×${a.quantite}</span>
            <span style="color:var(--or);">${(a.prix_unitaire * a.quantite).toFixed(2)} DH</span>
          </div>
        `).join('') : '<p style="color:var(--gris);">Aucun article</p>'}
        <div class="produit-item" style="font-weight:600; margin-top:8px; border-top:1px solid var(--bordure); padding-top:12px;">
          <span>Total</span>
          <span style="color:var(--or);">${commande.total.toFixed(2)} DH</span>
        </div>
      </div>

      <button class="btn-close" onclick="fermerModal()">Fermer</button>
    `;

    document.getElementById('modalOverlay').classList.add('active');
  }

  function fermerModal() {
    document.getElementById('modalOverlay').classList.remove('active');
  }

  // Fermer modal au clic extérieur
  document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) fermerModal();
  });

  // ============================================================
  // GESTION DES PRODUITS
  // ============================================================
  function ajouterProduit() {
    document.getElementById('formProduit').style.display = 'block';
    document.getElementById('titreFormProduit').textContent = 'Ajouter un produit';
    document.getElementById('editProduitId').value = '';
    document.getElementById('nomProduit').value = '';
    document.getElementById('prixProduit').value = '';
    document.getElementById('descProduit').value = '';
    // Désélectionner toutes les options
    const tailleSelect = document.getElementById('taillesProduit');
    for (let opt of tailleSelect.options) opt.selected = false;
    const couleurSelect = document.getElementById('couleursProduit');
    for (let opt of couleurSelect.options) opt.selected = false;
    document.getElementById('stockProduit').value = '10';
    document.getElementById('imageProduit').value = '';
    document.getElementById('imagesExtraProduit').value = '';
    document.getElementById('actifProduit').value = '1';
    window.scrollTo({ top: document.getElementById('formProduit').offsetTop - 100, behavior: 'smooth' });
  }

  function editerProduit(id) {
    // Rediriger vers une page d'édition avec l'ID
    window.location.href = 'admin-produit.php?id=' + id;
  }

  function supprimerProduit(id) {
    if (!confirm('Supprimer ce produit ?')) return;
    
    fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action_produit=delete&produit_id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        afficherToast('Produit supprimé', 'success');
        location.reload();
      } else {
        afficherToast('Erreur : ' + data.message, 'error');
      }
    })
    .catch(() => afficherToast('Erreur lors de la suppression', 'error'));
  }

  function annulerProduit() {
    document.getElementById('formProduit').style.display = 'none';
  }

  // Sauvegarder un produit via AJAX
  function sauvegarderProduit(event) {
    event.preventDefault();
    
    const form = document.getElementById('formProduit').querySelector('form');
    const formData = new FormData(form);
    
    fetch('admin.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        afficherToast('Produit sauvegardé avec succès', 'success');
        document.getElementById('formProduit').style.display = 'none';
        // Recharger la page pour voir les changements
        location.reload();
      } else {
        afficherToast('Erreur : ' + data.message, 'error');
      }
    })
    .catch(() => afficherToast('Erreur lors de la sauvegarde', 'error'));
    
    return false;
  }

  // ============================================================
  // INITIALISATION
  // ============================================================
  console.log('✅ Dashboard Admin prêt');
</script>

</body>
</html>