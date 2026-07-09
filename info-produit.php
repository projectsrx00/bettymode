<?php
// Configuration de la base de données
include('db.php');

// Récupérer l'ID du produit depuis l'URL
$idProduit = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les informations du produit
$produit = null;
if ($idProduit > 0) {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND actif = 1");
    $stmt->execute([$idProduit]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les couleurs du produit
$couleurs = [];
if ($produit) {
    $stmt = $pdo->prepare("
        SELECT c.nom, c.code_hex 
        FROM produit_couleurs pc 
        JOIN couleurs c ON pc.couleur_id = c.id 
        WHERE pc.produit_id = ?
    ");
    $stmt->execute([$idProduit]);
    $couleurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les tailles du produit
$tailles = [];
if ($produit) {
    $stmt = $pdo->prepare("
        SELECT t.valeur 
        FROM produit_tailles pt 
        JOIN tailles t ON pt.taille_id = t.id 
        WHERE pt.produit_id = ?
        ORDER BY 
            CASE 
                WHEN t.valeur = 'S' THEN 1
                WHEN t.valeur = 'M' THEN 2
                WHEN t.valeur = 'L' THEN 3
                WHEN t.valeur = 'XL' THEN 4
                WHEN t.valeur REGEXP '^[0-9]+$' THEN CAST(t.valeur AS UNSIGNED) + 10
                ELSE 99
            END
    ");
    $stmt->execute([$idProduit]);
    $tailles = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Détail produit — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .retour{padding:20px 0; display:block; color:var(--or); text-decoration:none; font-size:14px;}
  .retour:hover{text-decoration:underline;}
  
  .fiche-produit{display:grid; grid-template-columns:1fr 1fr; gap:40px; margin:20px 0 60px;}
  
  .galerie{position:relative;}
  .photo-principale{width:100%; height:450px; background:var(--carte); border-radius:14px; display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid var(--bordure);}
  .photo-principale img{width:100%; height:100%; object-fit:cover;}
  .photo-principale .placeholder{color:var(--gris); font-size:14px;}

  .infos-produit h1{font-family:'Playfair Display',serif; font-size:32px; margin:0 0 10px;}
  .infos-produit .prix{color:var(--or); font-size:28px; font-weight:600; margin-bottom:20px;}
  .infos-produit .description{color:var(--gris); line-height:1.6; margin-bottom:24px;}

  .section-choix{margin-bottom:20px;}
  .section-choix h4{font-size:14px; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; color:var(--blanc);}
  
  .tailles{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px;}
  .taille-btn{padding:10px 20px; border:1px solid var(--bordure); background:transparent; color:var(--blanc); border-radius:8px; cursor:pointer; transition:all .2s; font-family:'Poppins',sans-serif;}
  .taille-btn:hover{border-color:var(--or);}
  .taille-btn.active{background:var(--or); color:var(--blanc); border-color:var(--or);}
  
  .couleurs-choix{display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;}
  .couleur-btn{width:40px; height:40px; border-radius:50%; border:2px solid transparent; cursor:pointer; transition:all .2s; position:relative;}
  .couleur-btn:hover{transform:scale(1.1);}
  .couleur-btn.active{border-color:var(--or); box-shadow:0 0 0 2px var(--noir), 0 0 0 4px var(--or);}
  .couleur-btn .tooltip{position:absolute; bottom:-25px; left:50%; transform:translateX(-50%); font-size:10px; color:var(--gris); white-space:nowrap; opacity:0; transition:.2s;}
  .couleur-btn:hover .tooltip{opacity:1;}

  .quantite-ajout{display:flex; gap:12px; align-items:center; margin-top:24px; flex-wrap:wrap;}
  .quantite-ajout input[type=number]{width:70px; padding:12px; background:var(--noir-clair); border:1px solid var(--bordure); border-radius:8px; color:var(--blanc); text-align:center; font-size:16px;}
  .btn-ajouter-panier{padding:14px 30px; background:var(--or); color:var(--blanc); border:none; border-radius:8px; font-weight:600; cursor:pointer; text-transform:uppercase; letter-spacing:1px; transition:all .2s; font-family:'Poppins',sans-serif;}
  .btn-ajouter-panier:hover{background:#e6c200; transform:translateY(-2px);}
  .btn-ajouter-panier:disabled{background:var(--gris); cursor:not-allowed; transform:none;}

  .message-erreur{color:#e07b73; font-size:13px; margin-top:8px; display:none;}

  .reseaux-sociaux{display:flex; gap:12px; align-items:center;}
  .btn-social{padding:8px 16px; border-radius:25px; font-size:13px; text-decoration:none; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Poppins',sans-serif; font-weight:500;}
  .btn-whatsapp{background:#25D366; color:#fff;}
  .btn-whatsapp:hover{background:#1fb855; transform:translateY(-2px);}
  .btn-instagram{background:linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color:#fff;}
  .btn-instagram:hover{transform:translateY(-2px); opacity:0.9;}

  @media (max-width:860px){
    .retour{padding:16px 0;}
    .fiche-produit{grid-template-columns:1fr; gap:24px; margin:12px 0 44px;}
    .photo-principale{height:320px;}
    .infos-produit h1{font-size:26px;}
    .infos-produit .prix{font-size:24px;}
    .taille-btn{padding:9px 16px; font-size:13px;}
    .couleur-btn{width:36px; height:36px;}
    .quantite-ajout{gap:10px;}
    .quantite-ajout input[type=number]{width:64px; padding:10px;}
    .btn-ajouter-panier{flex:1; padding:14px 20px;}
  }
  @media (max-width:420px){
    .photo-principale{height:260px;}
    .infos-produit h1{font-size:22px;}
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="nav-wrap">
    <a href="index.php" class="logo">Betty<span>_</span>Mode</a>
    <nav class="nav-links" id="navLinks">
      <a href="index.php">Accueil</a>
      <a href="catalogue.php" class="actif">Catalogue</a>
      <div class="reseaux-mobile-menu">
        <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp">💬 WhatsApp</a>
        <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram">📷 Instagram</a>
      </div>
    </nav>
    <div class="nav-actions">
      <div class="reseaux-sociaux">
        <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp" title="WhatsApp">💬 WhatsApp</a>
        <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram" title="Instagram">📷 Instagram</a>
      </div>
      <a href="panier.php" class="icon-btn">🛒<span class="icon-label"> Panier</span> <span class="badge" id="badgePanier">0</span></a>
      <button class="burger" id="burgerBtn" aria-label="Ouvrir le menu" aria-expanded="false">
        <span class="burger-icone"><span></span><span></span><span></span></span>
      </button>
    </div>
  </div>
  <div class="menu-overlay" id="menuOverlay"></div>
</header>

<div class="conteneur">
  <a href="catalogue.php" class="retour">← Retour au catalogue</a>

  <div class="fiche-produit" id="ficheProduit">
    <div class="galerie">
      <div class="photo-principale" id="photoPrincipale">
        <?php if ($produit && !empty($produit['image'])): ?>
          <img id="imgProduit" src="<?= htmlspecialchars($produit['image']) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
          <span class="placeholder" id="placeholderPhoto" style="display:none;">Photo à venir</span>
        <?php else: ?>
          <span class="placeholder" id="placeholderPhoto">Photo à venir</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="infos-produit">
      <h1 id="nomProduit">
        <?= $produit ? htmlspecialchars($produit['nom']) : 'Produit non trouvé' ?>
      </h1>
      <div class="prix" id="prixProduit">
        <?= $produit ? number_format($produit['prix'], 2, ',', ' ') . ' DH' : '—' ?>
      </div>
      <div class="description" id="descProduit">
        <?= $produit ? nl2br(htmlspecialchars($produit['description'] ?? '')) : 'Désolé, ce produit n\'existe pas dans notre catalogue.' ?>
      </div>

      <?php if ($produit): ?>
        <div class="section-choix">
          <h4>Taille</h4>
          <div class="tailles" id="taillesContainer">
            <?php if (!empty($tailles)): ?>
              <?php foreach ($tailles as $taille): ?>
                <button class="taille-btn" onclick="selectionnerTaille(this, '<?= htmlspecialchars($taille) ?>')">
                  <?= htmlspecialchars($taille) ?>
                </button>
              <?php endforeach; ?>
            <?php else: ?>
              <span style="color:var(--gris); font-size:13px;">Aucune taille disponible</span>
            <?php endif; ?>
          </div>
          <div class="message-erreur" id="erreurTaille">Veuillez sélectionner une taille</div>
        </div>

        <div class="section-choix" id="sectionCouleurs" <?= empty($couleurs) ? 'style="display:none;"' : '' ?>>
          <h4>Couleur</h4>
          <div class="couleurs-choix" id="couleursContainer">
            <?php foreach ($couleurs as $couleur): ?>
              <div class="couleur-btn" style="background:<?= htmlspecialchars($couleur['code_hex']) ?>;" 
                   onclick="selectionnerCouleur(this, '<?= htmlspecialchars($couleur['nom']) ?>')"
                   title="<?= htmlspecialchars($couleur['nom']) ?>">
                <span class="tooltip"><?= htmlspecialchars($couleur['nom']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="message-erreur" id="erreurCouleur">Veuillez sélectionner une couleur</div>
        </div>

        <div class="quantite-ajout">
          <input type="number" id="quantite" value="1" min="1" max="<?= min($produit['stock'], 10) ?>">
          <button class="btn-ajouter-panier" id="btnAjouter" onclick="ajouterAuPanier()">Ajouter au panier</button>
        </div>

        <?php if ($produit['stock'] <= 0): ?>
          <div style="color:#e07b73; font-size:13px; margin-top:10px;">⚠️ Ce produit est actuellement en rupture de stock</div>
        <?php endif; ?>
      <?php else: ?>
        <div style="color:#e07b73; margin-top:20px;">Ce produit n'est pas disponible dans notre catalogue.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<footer>
  <div class="logo" style="font-size:20px; color:var(--blanc); margin-bottom:8px;">Betty<span style="color:var(--rose);">_</span>Mode</div>
  <p>Fashion designer · Maroc</p>
  <p>&copy; 2026 Betty Mode — Tous droits réservés</p>
</footer>

<script>
  // ============================================================
  // DONNÉES PRODUIT (passées depuis PHP vers JavaScript)
  // ============================================================
  const produitPHP = <?= $produit ? json_encode([
        'id' => $produit['id'],
        'nom' => $produit['nom'],
        'prix' => $produit['prix'],
        'image' => $produit['image'],
        'stock' => $produit['stock']
    ]) : 'null' ?>;

  const taillesPHP = <?= json_encode($tailles) ?>;
  const couleursPHP = <?= json_encode($couleurs) ?>;

  let tailleSelectionnee = null;
  let couleurSelectionnee = null;

  // ============================================================
  // SÉLECTIONS
  // ============================================================
  function selectionnerTaille(btn, taille) {
    document.querySelectorAll('.taille-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    tailleSelectionnee = taille;
    document.getElementById('erreurTaille').style.display = 'none';
  }

  function selectionnerCouleur(btn, couleur) {
    document.querySelectorAll('.couleur-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    couleurSelectionnee = couleur;
    document.getElementById('erreurCouleur').style.display = 'none';
  }

  // ============================================================
  // AJOUT AU PANIER
  // ============================================================
  function ajouterAuPanier() {
    if (!produitPHP) return;
    
    let valide = true;

    if (taillesPHP.length > 0 && !tailleSelectionnee) {
      document.getElementById('erreurTaille').style.display = 'block';
      valide = false;
    }

    if (couleursPHP.length > 0 && !couleurSelectionnee) {
      document.getElementById('erreurCouleur').style.display = 'block';
      valide = false;
    }

    if (!valide) return;

    const quantite = parseInt(document.getElementById('quantite').value) || 1;
    
    // Vérifier le stock
    if (produitPHP.stock !== null && quantite > produitPHP.stock) {
      alert('Désolé, nous n\'avons que ' + produitPHP.stock + ' exemplaire(s) en stock.');
      return;
    }
    
    const article = {
      id: produitPHP.id,
      nom: produitPHP.nom,
      prix: produitPHP.prix,
      image: produitPHP.image,
      taille: tailleSelectionnee || 'N/A',
      couleur: couleurSelectionnee || 'N/A',
      quantite: quantite
    };

    let panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    
    const index = panier.findIndex(item => 
      item.id === article.id && 
      item.taille === article.taille && 
      item.couleur === article.couleur
    );

    if (index > -1) {
      panier[index].quantite += quantite;
    } else {
      panier.push(article);
    }

    localStorage.setItem('panierBetty', JSON.stringify(panier));
    majBadgePanier();
    
    // Feedback
    const btn = document.getElementById('btnAjouter');
    const originalText = btn.textContent;
    btn.textContent = '✓ Ajouté !';
    btn.style.background = '#4CAF50';
    setTimeout(() => {
      btn.textContent = originalText;
      btn.style.background = 'var(--or)';
    }, 1500);
  }

  // ============================================================
  // BADGE PANIER
  // ============================================================
  function majBadgePanier() {
    const panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    const total = panier.reduce((sum, item) => sum + item.quantite, 0);
    document.getElementById('badgePanier').textContent = total;
  }

  // ============================================================
  // MENU BURGER
  // ============================================================
  const burgerBtn = document.getElementById('burgerBtn');
  const navLinks = document.getElementById('navLinks');
  const menuOverlay = document.getElementById('menuOverlay');
  
  function toggleMenu(forceClose) {
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

  // ============================================================
  // INITIALISATION
  // ============================================================
  majBadgePanier();
  
  // Définir la limite max de quantité selon le stock
  <?php if ($produit && $produit['stock'] > 0): ?>
    document.getElementById('quantite').max = <?= min($produit['stock'], 10) ?>;
  <?php endif; ?>
</script>
</body>
</html>