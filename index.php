<?php
include('db.php');

// Récupérer les produits pour l'affichage (limité à 3 pour l'accueil)
$stmt = $pdo->prepare("SELECT * FROM produits WHERE actif = 1 ORDER BY id LIMIT 3");
$stmt->execute();
$produitsAccueil = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les couleurs pour chaque produit (pour l'affichage des cercles)
function getCouleursProduit($pdo, $produitId) {
    $stmt = $pdo->prepare("
        SELECT c.nom, c.code_hex 
        FROM produit_couleurs pc 
        JOIN couleurs c ON pc.couleur_id = c.id 
        WHERE pc.produit_id = ?
    ");
    $stmt->execute([$produitId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les tailles pour chaque produit
function getTaillesProduit($pdo, $produitId) {
    $stmt = $pdo->prepare("
        SELECT t.valeur 
        FROM produit_tailles pt 
        JOIN tailles t ON pt.taille_id = t.id 
        WHERE pt.produit_id = ?
        ORDER BY t.id
    ");
    $stmt->execute([$produitId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accueil — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .hero{
    position:relative; padding:110px 24px 90px; text-align:center;
    background:
      radial-gradient(circle at 20% 20%, rgba(217,165,160,.08), transparent 40%),
      radial-gradient(circle at 80% 80%, rgba(203,161,53,.08), transparent 40%);
    border-bottom:1px solid var(--bordure);
  }
  .hero h1{font-size:52px; margin:0 0 18px; line-height:1.15;}
  .hero h1 em{color:var(--rose); font-style:italic;}
  .hero p{color:var(--gris); font-size:16px; max-width:520px; margin:0 auto 34px;}
  .hero .actions{display:flex; justify-content:center; gap:16px; flex-wrap:wrap;}

  .section-titre{display:flex; align-items:baseline; justify-content:space-between; margin:70px 0 30px;}
  .section-titre h2{font-size:28px; margin:0;}
  .section-titre a{color:var(--rose); font-size:14px; text-transform:uppercase; letter-spacing:.5px;}

  .grille-produits{display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:26px; margin-bottom:40px;}
  .carte-produit{background:var(--carte); border:1px solid var(--bordure); border-radius:14px; overflow:hidden; transition:transform .2s, border-color .2s; cursor:pointer;}
  .carte-produit:hover{transform:translateY(-4px); border-color:var(--rose);}
  .carte-produit .img{height:280px; background:var(--noir-clair); display:flex; align-items:center; justify-content:center; color:var(--gris); font-size:13px; position:relative; overflow:hidden;}
  .carte-produit .img img{width:100%; height:100%; object-fit:cover;}
  .carte-produit .img .placeholder{position:absolute; color:#fdf8f4; font-size:12px; background:rgba(58,43,38,0.75); padding:6px 12px; border-radius:4px;}
  .carte-corps{padding:18px;}
  .carte-corps h3{font-size:18px; margin:0 0 6px; font-family:'Playfair Display',serif; font-weight:500;}
  .carte-corps .options{font-size:12px; color:var(--gris); margin-bottom:8px;}
  .carte-corps .options span{background:var(--noir-clair); padding:2px 8px; border-radius:12px; margin-right:4px; font-size:11px;}
  .carte-corps .couleurs{display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap;}
  .cercle-couleur{width:18px; height:18px; border-radius:50%; border:1px solid var(--bordure);}
  .carte-corps .prix{color:var(--or); font-size:20px; font-weight:600; margin-bottom:14px;}

  .reseaux-sociaux{display:flex; gap:12px; align-items:center;}
  .btn-social{padding:8px 16px; border-radius:25px; font-size:13px; text-decoration:none; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Poppins',sans-serif; font-weight:500;}
  .btn-whatsapp{background:#25D366; color:#fff;}
  .btn-whatsapp:hover{background:#1fb855; transform:translateY(-2px);}
  .btn-instagram{background:linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color:#fff;}
  .btn-instagram:hover{transform:translateY(-2px); opacity:0.9;}

  .atouts{display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:30px; padding:70px 0;}
  .atout{text-align:center;}
  .atout .icone{font-size:30px; margin-bottom:14px;}
  .atout h3{font-size:16px; margin:0 0 8px;}
  .atout p{color:var(--gris); font-size:13px; margin:0;}

  @media (max-width:860px){
    .hero{padding:70px 20px 60px;}
    .hero h1{font-size:32px;}
    .hero p{font-size:15px;}
    .reseaux-mobile{display:flex !important; flex-direction:column; align-items:center; gap:12px; margin-top:22px;}
    .reseaux-mobile .btn-social{width:100%; max-width:300px; justify-content:center;}
    .section-titre{margin:44px 0 22px; flex-wrap:wrap; gap:8px;}
    .section-titre h2{font-size:22px;}
    .grille-produits{grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:14px;}
    .carte-produit .img{height:190px;}
    .carte-corps{padding:12px;}
    .carte-corps h3{font-size:15px;}
    .carte-corps .prix{font-size:17px; margin-bottom:10px;}
    .atouts{padding:44px 0; gap:26px;}
  }
  @media (max-width:420px){
    .hero h1{font-size:26px;}
    .grille-produits{grid-template-columns:repeat(2, 1fr); gap:10px;}
    .carte-produit .img{height:150px;}
  }
  .reseaux-mobile{display:none;}
</style>
</head>
<body>

<header class="site-header">
  <div class="nav-wrap">
    <a href="index.php" class="logo">Betty<span>_</span>Mode</a>
    <nav class="nav-links" id="navLinks">
      <a href="index.php" class="actif">Accueil</a>
      <a href="catalogue.php">Catalogue</a>
      <div class="reseaux-mobile-menu">
        <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp">💬 WhatsApp</a>
        <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram">📷 Instagram</a>
      </div>
    </nav>
    <div class="nav-actions">
      <div class="reseaux-sociaux">
        <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp" title="WhatsApp">
          💬 WhatsApp
        </a>
        <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram" title="Instagram">
          📷 Instagram
        </a>
      </div>
      <a href="panier.php" class="icon-btn">🛒<span class="icon-label"> Panier</span> <span class="badge" id="badgePanier">0</span></a>
      <button class="burger" id="burgerBtn" aria-label="Ouvrir le menu" aria-expanded="false">
        <span class="burger-icone"><span></span><span></span><span></span></span>
      </button>
    </div>
  </div>
  <div class="menu-overlay" id="menuOverlay"></div>
</header>

<section class="hero">
  <h1>L'élégance au quotidien,<br><em>signée Betty Mode</em></h1>
  <p>Des pièces uniques pensées pour sublimer chaque silhouette. Découvrez notre collection exclusive de vêtements pour femme.</p>
  <div class="actions">
    <a href="catalogue.php" class="btn btn-or">Découvrir le catalogue</a>
  </div>
  <div class="reseaux-mobile">
    <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp">
      💬 WhatsApp
    </a>
    <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram">
      📷 Instagram
    </a>
  </div>
</section>

<div class="conteneur">
  <div class="section-titre">
    <h2>Notre Collection</h2>
    <a href="catalogue.php">Voir tout le catalogue →</a>
  </div>

  <div class="grille-produits">
    <?php if (empty($produitsAccueil)): ?>
      <p>Aucun produit disponible pour le moment.</p>
    <?php else: ?>
      <?php foreach ($produitsAccueil as $produit): 
        $couleurs = getCouleursProduit($pdo, $produit['id']);
        $tailles = getTaillesProduit($pdo, $produit['id']);
      ?>
        <div class="carte-produit" onclick="location.href='info-produit.php?id=<?= $produit['id'] ?>'">
          <div class="img">
            <?php if ($produit['image'] && file_exists($produit['image'])): ?>
              <img src="<?= htmlspecialchars($produit['image']) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>">
            <?php else: ?>
              <span class="placeholder">Photo : <?= htmlspecialchars($produit['nom']) ?></span>
            <?php endif; ?>
          </div>
          <div class="carte-corps">
            <h3><?= htmlspecialchars($produit['nom']) ?></h3>
            <?php if (!empty($tailles)): ?>
              <div class="options">
                Tailles :
                <?php foreach ($tailles as $taille): ?>
                  <span><?= htmlspecialchars($taille) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($couleurs)): ?>
              <div class="couleurs">
                <?php foreach ($couleurs as $couleur): ?>
                  <span class="cercle-couleur" style="background:<?= htmlspecialchars($couleur['code_hex']) ?>;" title="<?= htmlspecialchars($couleur['nom']) ?>"></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="prix"><?= number_format($produit['prix'], 2, ',', ' ') ?> DH</div>
            <a href="info-produit.php?id=<?= $produit['id'] ?>" class="btn btn-outline btn-sm">Voir le produit</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="atouts">
    <div class="atout">
      <div class="icone">✂️</div>
      <h3>Créations originales</h3>
      <p>Chaque pièce est pensée et sélectionnée avec soin par Betty.</p>
    </div>
    <div class="atout">
      <div class="icone">🚚</div>
      <h3>Livraison rapide</h3>
      <p>Recevez vos commandes en un temps record, partout au Maroc.</p>
    </div>
    <div class="atout">
      <div class="icone">💵</div>
      <h3>Paiement à la livraison</h3>
      <p>Réglez en espèces, en toute simplicité, à la réception de votre colis.</p>
    </div>
  </div>
</div>

<footer>
  <div class="logo" style="font-size:20px; color:var(--blanc); margin-bottom:8px;">Betty<span style="color:var(--rose);">_</span>Mode</div>
  <p>Fashion designer · Maroc</p>
  <div style="display:flex; justify-content:center; gap:16px; margin:16px 0; flex-wrap:wrap;">
    <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp">
      💬 WhatsApp
    </a>
    <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram">
      📷 Instagram
    </a>
  </div>
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

  // Mise à jour badge panier
  function majBadgePanier(){
    const panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    const total = panier.reduce((sum, item) => sum + item.quantite, 0);
    document.getElementById('badgePanier').textContent = total;
  }
  majBadgePanier();
</script>
</body>
</html>