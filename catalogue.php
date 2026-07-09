<?php
// Configuration de la base de données
include('db.php');

// Récupérer tous les produits actifs
$stmt = $pdo->prepare("SELECT * FROM produits WHERE actif = 1 ORDER BY id");
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les couleurs pour chaque produit
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

// Fonction pour générer le HTML du carrousel
function genererCarrousel($produit) {
    $images = [];
    
    // Image principale
    if (!empty($produit['image'])) {
        $images[] = $produit['image'];
    }
    
    // Images supplémentaires (stockées en JSON dans images_extra)
    if (!empty($produit['images_extra'])) {
        // Décoder le JSON
        $extraImages = json_decode($produit['images_extra'], true);
        if (is_array($extraImages)) {
            foreach ($extraImages as $img) {
                if (!empty($img)) {
                    $images[] = $img;
                }
            }
        }
    }
    
    // Si aucune image, afficher un placeholder
    if (empty($images)) {
        return '<span class="placeholder">Photo : ' . htmlspecialchars($produit['nom']) . '</span>';
    }
    
    // Si une seule image, affichage simple
    if (count($images) == 1) {
        return '<img src="' . htmlspecialchars($images[0]) . '" alt="' . htmlspecialchars($produit['nom']) . '" onerror="this.style.display=\'none\'; this.parentElement.querySelector(\'.placeholder\').style.display=\'block\';">';
    }
    
    // Carrousel (plusieurs images)
    $html = '<div class="carousel-container" data-carousel>';
    $html .= '<div class="carousel-slides" data-slides>';
    foreach ($images as $image) {
        $html .= '<div class="carousel-slide"><img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($produit['nom']) . '" loading="lazy" onerror="this.style.display=\'none\';"></div>';
    }
    $html .= '</div>';
    $html .= '<button class="carousel-btn prev" data-prev aria-label="Image précédente">‹</button>';
    $html .= '<button class="carousel-btn next" data-next aria-label="Image suivante">›</button>';
    $html .= '<div class="carousel-indicators" data-indicators></div>';
    $html .= '</div>';
    $html .= '<span class="placeholder" style="display:none;">' . htmlspecialchars($produit['nom']) . '</span>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catalogue — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .page-titre{padding:60px 24px 20px; text-align:center;}
  .page-titre h1{font-size:38px; margin:0 0 8px;}
  .page-titre p{color:var(--gris); margin:0;}

  .barre-outils{display:flex; flex-wrap:wrap; gap:14px; justify-content:space-between; align-items:center; margin:30px 0;}
  .barre-outils input[type=text]{flex:1; min-width:220px; padding:11px 16px; background:var(--carte); border:1px solid var(--bordure); border-radius:30px; color:var(--blanc); font-family:'Poppins',sans-serif; font-size:14px;}
  .barre-outils select{padding:11px 16px; background:var(--carte); border:1px solid var(--bordure); border-radius:30px; color:var(--blanc); font-family:'Poppins',sans-serif; font-size:14px;}

  .grille-produits{display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:26px; margin-bottom:70px;}
  .carte-produit{background:var(--carte); border:1px solid var(--bordure); border-radius:14px; overflow:hidden; transition:transform .2s, border-color .2s; scroll-margin-top:100px; cursor:pointer;}
  .carte-produit:hover{transform:translateY(-4px); border-color:var(--rose);}
  .carte-produit .img{height:300px; background:var(--noir-clair); display:flex; align-items:center; justify-content:center; color:var(--gris); font-size:13px; position:relative; overflow:hidden;}
  .carte-produit .img img{width:100%; height:100%; object-fit:cover;}
  .carte-produit .img .placeholder{position:absolute; color:#fdf8f4; font-size:12px; background:rgba(58,43,38,0.75); padding:6px 12px; border-radius:4px;}
  .carte-corps{padding:18px;}
  .carte-corps h3{font-size:18px; margin:0 0 6px; font-weight:500; font-family:'Playfair Display',serif;}
  .carte-corps .desc{font-size:13px; color:var(--gris); margin-bottom:12px; min-height:36px;}
  .carte-corps .prix{color:var(--or); font-size:20px; font-weight:600; margin-bottom:12px;}
  .carte-corps .options{font-size:12px; color:var(--gris); margin-bottom:8px;}
  .carte-corps .options span{background:var(--noir-clair); padding:2px 8px; border-radius:12px; margin-right:4px; font-size:11px;}
  .carte-corps .couleurs{display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap;}
  .cercle-couleur{width:18px; height:18px; border-radius:50%; border:1px solid var(--bordure);}
  .btn-voir{border:1px solid var(--or); color:var(--or); background:transparent; padding:8px 18px; border-radius:6px; font-size:13px; cursor:pointer; transition:all .2s; text-transform:uppercase; letter-spacing:1px;}
  .btn-voir:hover{background:var(--or); color:var(--blanc);}

  /* --- Styles du carrousel --- */
  .carousel-container {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
  }
  .carousel-slides {
    display: flex;
    width: 100%;
    height: 100%;
    transition: transform 0.4s ease-in-out;
  }
  .carousel-slide {
    min-width: 100%;
    height: 100%;
  }
  .carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.5);
    border: none;
    color: white;
    font-size: 24px;
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 30px;
    transition: .2s;
    line-height: 1;
    z-index: 5;
    font-family: sans-serif;
  }
  .carousel-btn:hover { background: rgba(0,0,0,0.8); }
  .carousel-btn.prev { left: 6px; }
  .carousel-btn.next { right: 6px; }
  .carousel-indicators {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 5;
  }
  .carousel-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.4);
    border: none;
    cursor: pointer;
    transition: .2s;
    padding: 0;
  }
  .carousel-dot.active { background: white; }

  .toast{
    position:fixed; bottom:24px; right:24px; background:var(--carte); border:1px solid var(--rose);
    color:var(--blanc); padding:14px 22px; border-radius:10px; font-size:14px;
    opacity:0; transform:translateY(10px); transition:.25s; pointer-events:none; z-index:1000;
  }
  .toast.show{opacity:1; transform:translateY(0);}

  .reseaux-sociaux{display:flex; gap:12px; align-items:center;}
  .btn-social{padding:8px 16px; border-radius:25px; font-size:13px; text-decoration:none; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Poppins',sans-serif; font-weight:500;}
  .btn-whatsapp{background:#25D366; color:#fff;}
  .btn-whatsapp:hover{background:#1fb855; transform:translateY(-2px);}
  .btn-instagram{background:linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color:#fff;}
  .btn-instagram:hover{transform:translateY(-2px); opacity:0.9;}

  @media (max-width:860px){
    .page-titre{padding:40px 20px 14px;}
    .page-titre h1{font-size:28px;}
    .page-titre p{font-size:13px;}
    .barre-outils{margin:20px 0; gap:10px;}
    .barre-outils input[type=text]{min-width:100%;}
    .barre-outils select{flex:1; min-width:140px;}
    .grille-produits{grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:14px; margin-bottom:44px;}
    .carte-produit .img{height:190px;}
    .carte-corps{padding:12px;}
    .carte-corps h3{font-size:15px;}
    .carte-corps .desc{font-size:12px; min-height:auto;}
    .carte-corps .prix{font-size:17px; margin-bottom:10px;}
    .btn-voir{width:100%; padding:10px 18px;}
    .toast{left:16px; right:16px; bottom:16px; text-align:center;}
  }
  @media (max-width:420px){
    .grille-produits{grid-template-columns:repeat(2, 1fr); gap:10px;}
    .carte-produit .img{height:150px;}
    .carte-corps .options span{font-size:10px; padding:2px 6px;}
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

<div class="page-titre">
  <h1>Notre Collection</h1>
  <p>Des pièces uniques pour un style affirmé</p>
</div>

<div class="conteneur">
  <div class="barre-outils">
    <input type="text" id="recherche" placeholder="Rechercher un produit...">
    <select id="tri">
      <option value="recent">Plus récents</option>
      <option value="prix_asc">Prix croissant</option>
      <option value="prix_desc">Prix décroissant</option>
      <option value="nom">Nom (A-Z)</option>
    </select>
  </div>

  <div class="grille-produits" id="grilleProduits">
    <?php if (empty($produits)): ?>
      <p>Aucun produit disponible pour le moment.</p>
    <?php else: ?>
      <?php foreach ($produits as $produit): 
        $couleurs = getCouleursProduit($pdo, $produit['id']);
        $tailles = getTaillesProduit($pdo, $produit['id']);
        $descriptionCourte = substr($produit['description'] ?? '', 0, 60);
        if (strlen($produit['description'] ?? '') > 60) {
            $descriptionCourte .= '...';
        }
      ?>
        <div class="carte-produit" id="produit-<?= $produit['id'] ?>" data-nom="<?= htmlspecialchars($produit['nom']) ?>" data-prix="<?= htmlspecialchars($produit['prix']) ?>" data-url="info-produit.php?id=<?= $produit['id'] ?>">
          <div class="img">
            <?= genererCarrousel($produit) ?>
          </div>
          <div class="carte-corps">
            <h3><?= htmlspecialchars($produit['nom']) ?></h3>
            <div class="desc"><?= htmlspecialchars($descriptionCourte) ?></div>
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
            <button class="btn-voir" onclick="location.href='info-produit.php?id=<?= $produit['id'] ?>'">Voir le produit</button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<footer>
  <div class="logo" style="font-size:20px; color:var(--blanc); margin-bottom:8px;">Betty<span style="color:var(--rose);">_</span>Mode</div>
  <p>Fashion designer · Maroc</p>
  <p>&copy; 2026 Betty Mode — Tous droits réservés</p>
</footer>

<div class="toast" id="toast">Produit ajouté au panier ✓</div>

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

  // Recherche live
  document.getElementById('recherche').addEventListener('input', function(e){
    const terme = e.target.value.toLowerCase();
    document.querySelectorAll('.carte-produit').forEach(carte => {
      const nom = carte.dataset.nom.toLowerCase();
      carte.style.display = nom.includes(terme) ? '' : 'none';
    });
  });

  // Tri
  document.getElementById('tri').addEventListener('change', function(e){
    const grille = document.getElementById('grilleProduits');
    const cartes = Array.from(grille.children);
    const val = e.target.value;

    cartes.sort((a, b) => {
      if (val === 'prix_asc') return parseFloat(a.dataset.prix) - parseFloat(b.dataset.prix);
      if (val === 'prix_desc') return parseFloat(b.dataset.prix) - parseFloat(a.dataset.prix);
      if (val === 'nom') return a.dataset.nom.localeCompare(b.dataset.nom);
      return 0;
    });
    cartes.forEach(c => grille.appendChild(c));
    // Réinitialiser les carrousels après le tri
    initCarousels();
  });

  // ----- Carrousel -----
  function initCarousels() {
    document.querySelectorAll('.carousel-container').forEach(container => {
      const slides = container.querySelector('[data-slides]');
      const prevBtn = container.querySelector('[data-prev]');
      const nextBtn = container.querySelector('[data-next]');
      const indicators = container.querySelector('[data-indicators]');
      if (!slides) return;
      const totalSlides = slides.children.length;
      if (totalSlides === 0) return;
      let currentIndex = 0;

      indicators.innerHTML = '';
      for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('button');
        dot.classList.add('carousel-dot');
        if (i === 0) dot.classList.add('active');
        dot.setAttribute('data-index', i);
        dot.addEventListener('click', () => goTo(i));
        indicators.appendChild(dot);
      }

      function goTo(index) {
        if (index < 0) index = totalSlides - 1;
        if (index >= totalSlides) index = 0;
        currentIndex = index;
        slides.style.transform = `translateX(-${currentIndex * 100}%)`;
        indicators.querySelectorAll('.carousel-dot').forEach((dot, i) => {
          dot.classList.toggle('active', i === currentIndex);
        });
      }

      function next() { goTo(currentIndex + 1); }
      function prev() { goTo(currentIndex - 1); }

      if (prevBtn) prevBtn.addEventListener('click', prev);
      if (nextBtn) nextBtn.addEventListener('click', next);

      let interval = setInterval(next, 4000);
      container.addEventListener('mouseenter', () => clearInterval(interval));
      container.addEventListener('mouseleave', () => {
        clearInterval(interval);
        interval = setInterval(next, 4000);
      });
      container._interval = interval;
    });
  }

  document.addEventListener('DOMContentLoaded', initCarousels);
</script>

</body>
</html>