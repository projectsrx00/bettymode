<?php
// Inclusion de la connexion à la base de données
include('db.php');

// Récupérer l'ID du produit depuis l'URL
$produitId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les informations du produit
$produit = null;
if ($produitId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$produitId]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les tailles associées
    $stmt = $pdo->prepare("SELECT taille_id FROM produit_tailles WHERE produit_id = ?");
    $stmt->execute([$produitId]);
    $taillesAssociees = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupérer les couleurs associées
    $stmt = $pdo->prepare("SELECT couleur_id FROM produit_couleurs WHERE produit_id = ?");
    $stmt->execute([$produitId]);
    $couleursAssociees = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Récupérer toutes les tailles disponibles
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

// Récupérer toutes les couleurs disponibles
$stmt = $pdo->prepare("SELECT * FROM couleurs ORDER BY nom");
$stmt->execute();
$couleursDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si le produit n'existe pas, rediriger vers admin.php
if (!$produit && $produitId > 0) {
    header('Location: admin.php');
    exit;
}

// === TRAITEMENT DU FORMULAIRE ===
$message = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $nom = trim($_POST['nom'] ?? '');
        $prix = floatval($_POST['prix'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $image = trim($_POST['image'] ?? '');
        $actif = isset($_POST['actif']) ? intval($_POST['actif']) : 1;
        $tailles = isset($_POST['tailles']) ? array_map('intval', $_POST['tailles']) : [];
        $couleurs = isset($_POST['couleurs']) ? array_map('intval', $_POST['couleurs']) : [];
        
        if (empty($nom) || $prix <= 0) {
            throw new Exception('Le nom et le prix sont requis');
        }
        
        $pdo->beginTransaction();
        
        // Mettre à jour le produit
        $stmt = $pdo->prepare("
            UPDATE produits 
            SET nom = ?, prix = ?, description = ?, stock = ?, image = ?, actif = ?, date_modification = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nom, $prix, $description, $stock, $image, $actif, $produitId]);
        
        // Supprimer les anciennes relations
        $pdo->prepare("DELETE FROM produit_tailles WHERE produit_id = ?")->execute([$produitId]);
        $pdo->prepare("DELETE FROM produit_couleurs WHERE produit_id = ?")->execute([$produitId]);
        
        // Ajouter les nouvelles tailles
        if (!empty($tailles)) {
            $stmt = $pdo->prepare("INSERT INTO produit_tailles (produit_id, taille_id) VALUES (?, ?)");
            foreach ($tailles as $tailleId) {
                $stmt->execute([$produitId, $tailleId]);
            }
        }
        
        // Ajouter les nouvelles couleurs
        if (!empty($couleurs)) {
            $stmt = $pdo->prepare("INSERT INTO produit_couleurs (produit_id, couleur_id) VALUES (?, ?)");
            foreach ($couleurs as $couleurId) {
                $stmt->execute([$produitId, $couleurId]);
            }
        }
        
        $pdo->commit();
        $message = "Produit mis à jour avec succès !";
        
        // Recharger les données du produit
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
        $stmt->execute([$produitId]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recharger les relations
        $stmt = $pdo->prepare("SELECT taille_id FROM produit_tailles WHERE produit_id = ?");
        $stmt->execute([$produitId]);
        $taillesAssociees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("SELECT couleur_id FROM produit_couleurs WHERE produit_id = ?");
        $stmt->execute([$produitId]);
        $couleursAssociees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erreur = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier le produit — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .page-header {
    padding: 30px 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
  }
  .page-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    margin: 0;
  }
  .page-header .subtitle {
    color: var(--gris);
    font-size: 14px;
  }
  .btn-retour {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--gris);
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s;
  }
  .btn-retour:hover {
    color: var(--blanc);
  }

  .form-container {
    background: var(--carte);
    border: 1px solid var(--bordure);
    border-radius: 14px;
    padding: 30px;
    max-width: 800px;
    margin: 0 auto;
  }

  .form-container h2 {
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
    min-width: 200px;
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
    height: 100px;
  }
  .form-group .help-text {
    font-size: 11px;
    color: var(--gris);
    margin-top: 4px;
  }

  .form-actions {
    display: flex;
    gap: 12px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid var(--bordure);
    flex-wrap: wrap;
  }

  .btn-or {
    background: var(--or);
    color: var(--blanc);
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s;
    font-size: 14px;
  }
  .btn-or:hover {
    background: #e6c200;
    transform: translateY(-2px);
  }
  .btn-outline {
    background: transparent;
    border: 1px solid var(--bordure);
    color: var(--blanc);
    padding: 12px 30px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s;
    font-size: 14px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .btn-outline:hover {
    border-color: var(--or);
    color: var(--or);
  }
  .btn-danger {
    background: transparent;
    border: 1px solid #EF5350;
    color: #EF5350;
    padding: 12px 30px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s;
    font-size: 14px;
  }
  .btn-danger:hover {
    background: rgba(239, 83, 80, 0.1);
  }

  .message-success {
    background: rgba(76, 175, 80, 0.15);
    border: 1px solid #4CAF50;
    color: #66BB6A;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
  }
  .message-error {
    background: rgba(244, 67, 54, 0.15);
    border: 1px solid #EF5350;
    color: #EF5350;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
  }

  .current-image {
    display: flex;
    align-items: center;
    gap: 16px;
    background: var(--noir);
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--bordure);
  }
  .current-image .preview {
    width: 80px;
    height: 80px;
    background: var(--noir-clair);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .current-image .preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .current-image .info {
    font-size: 13px;
    color: var(--gris);
  }

  @media (max-width: 768px) {
    .page-header h1 { font-size: 22px; }
    .form-container { padding: 20px; }
    .form-row { flex-direction: column; }
    .form-group { min-width: 100%; }
    .form-actions { flex-direction: column; }
    .form-actions .btn-or,
    .form-actions .btn-outline,
    .form-actions .btn-danger {
      width: 100%;
      justify-content: center;
    }
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="nav-wrap">
    <a href="admin.php" class="logo">Betty<span>_</span>Mode</a>
    <nav class="nav-links" id="navLinks">
      <a href="admin.php">Dashboard</a>
      <a href="admin.php#section-produits" class="actif">Produits</a>
    </nav>
    <div class="nav-actions">
      <button class="burger" id="burgerBtn" aria-label="Ouvrir le menu" aria-expanded="false">
        <span class="burger-icone"><span></span><span></span><span></span></span>
      </button>
    </div>
  </div>
  <div class="menu-overlay" id="menuOverlay"></div>
</header>

<div class="conteneur">
  
  <div class="page-header">
    <div>
      <h1>✏️ Modifier le produit</h1>
      <div class="subtitle">ID #<?= $produitId ?> · <?= htmlspecialchars($produit['nom'] ?? '') ?></div>
    </div>
    <a href="admin.php" class="btn-retour">← Retour au dashboard</a>
  </div>

  <?php if ($message): ?>
    <div class="message-success">✅ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($erreur): ?>
    <div class="message-error">❌ <?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <?php if ($produit): ?>
    <div class="form-container">
      <h2>Informations du produit</h2>
      
      <form method="POST" action="admin-produit.php?id=<?= $produitId ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        
        <div class="form-row">
          <div class="form-group">
            <label>Nom du produit *</label>
            <input type="text" name="nom" id="nomProduit" value="<?= htmlspecialchars($produit['nom']) ?>" required>
          </div>
          <div class="form-group">
            <label>Prix (DH) *</label>
            <input type="number" name="prix" id="prixProduit" value="<?= $produit['prix'] ?>" step="0.01" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" id="descProduit"><?= htmlspecialchars($produit['description'] ?? '') ?></textarea>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Tailles disponibles</label>
            <select name="tailles[]" id="taillesProduit" multiple>
              <?php foreach ($taillesDisponibles as $t): ?>
                <option value="<?= $t['id'] ?>" <?= in_array($t['id'], $taillesAssociees ?? []) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['valeur']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="help-text">Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs tailles</div>
          </div>
          <div class="form-group">
            <label>Couleurs disponibles</label>
            <select name="couleurs[]" id="couleursProduit" multiple>
              <?php foreach ($couleursDisponibles as $c): ?>
                <option value="<?= $c['id'] ?>" <?= in_array($c['id'], $couleursAssociees ?? []) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="help-text">Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs couleurs</div>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Stock</label>
            <input type="number" name="stock" id="stockProduit" value="<?= $produit['stock'] ?>" min="0">
          </div>
          <div class="form-group">
            <label>Image (chemin)</label>
            <input type="text" name="image" id="imageProduit" value="<?= htmlspecialchars($produit['image'] ?? '') ?>" placeholder="ex: images/produit.jpg">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Image actuelle</label>
            <?php if (!empty($produit['image'])): ?>
              <div class="current-image">
                <div class="preview">
                  <img src="<?= htmlspecialchars($produit['image']) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\'color:var(--gris);\'>Image non trouvée</span>';">
                </div>
                <div class="info">
                  <div><strong>Fichier :</strong> <?= basename($produit['image']) ?></div>
                  <div style="font-size:12px;">Modifiez le chemin ci-dessus pour changer l'image</div>
                </div>
              </div>
            <?php else: ?>
              <div class="current-image">
                <div class="preview" style="color:var(--gris); font-size:12px;">Aucune image</div>
                <div class="info">Ajoutez un chemin d'image ci-dessus</div>
              </div>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label>Statut</label>
            <select name="actif" id="actifProduit">
              <option value="1" <?= $produit['actif'] == 1 ? 'selected' : '' ?>>Actif</option>
              <option value="0" <?= $produit['actif'] == 0 ? 'selected' : '' ?>>Inactif</option>
            </select>
            <div class="help-text">Un produit inactif n'apparaît pas dans le catalogue</div>
          </div>
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn-or">💾 Sauvegarder les modifications</button>
          <a href="admin.php" class="btn-outline">Annuler</a>
          <button type="button" class="btn-danger" onclick="supprimerProduit(<?= $produitId ?>)">🗑 Supprimer le produit</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div style="text-align:center; padding:60px 0; color:var(--gris);">
      <div style="font-size:48px; margin-bottom:20px;">🔍</div>
      <h2>Produit non trouvé</h2>
      <p>Le produit que vous recherchez n'existe pas dans la base de données.</p>
      <a href="admin.php" class="btn-or" style="display:inline-block; margin-top:20px; text-decoration:none;">Retour au dashboard</a>
    </div>
  <?php endif; ?>

</div>

<footer>
  <div class="logo" style="font-size:20px; color:var(--blanc); margin-bottom:8px;">Betty<span style="color:var(--rose);">_</span>Mode</div>
  <p>Fashion designer · Maroc</p>
  <p>&copy; 2026 Betty Mode — Tous droits réservés</p>
</footer>

<script>
  // Menu burger
  const burgerBtn = document.getElementById('burgerBtn');
  const navLinks = document.getElementById('navLinks');
  const menuOverlay = document.getElementById('menuOverlay');
  
  function toggleMenu(forceClose){
    const ouvert = forceClose ? false : !navLinks.classList.contains('ouvert');
    navLinks.classList.toggle('ouvert', ouvert);
    menuOverlay.classList.toggle('visible', ouvert);
    burgerBtn.classList.toggle('ouvert', ouvert);
    burgerBtn.setAttribute('aria-expanded', ouvert);
    document.body.classList.toggle('menu-ouvert', ouvert);
  }
  burgerBtn.addEventListener('click', () => toggleMenu());
  menuOverlay.addEventListener('click', () => toggleMenu(true));
  navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => toggleMenu(true)));

  // Supprimer un produit
  function supprimerProduit(id) {
    if (!confirm('⚠️ Êtes-vous sûr de vouloir supprimer définitivement ce produit ? Cette action est irréversible.')) return;
    
    fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action_produit=delete&produit_id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('✅ Produit supprimé avec succès');
        window.location.href = 'admin.php';
      } else {
        alert('❌ Erreur : ' + data.message);
      }
    })
    .catch(() => alert('❌ Erreur lors de la suppression'));
  }
</script>

</body>
</html>