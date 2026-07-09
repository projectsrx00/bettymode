<?php
include('db.php');

// Récupérer tous les produits pour les références
$stmt = $pdo->prepare("SELECT id, nom, prix, image FROM produits WHERE actif = 1");
$stmt->execute();
$produitsRef = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau associatif pour un accès facile
$produitsMap = [];
foreach ($produitsRef as $p) {
    $produitsMap[$p['id']] = $p;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon panier — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .page-titre{padding:60px 24px 30px; text-align:center;}
  .page-titre h1{font-size:38px; margin:0;}

  .panier-vide{text-align:center; padding:80px 24px; color:var(--gris); display:none;}
  .panier-vide .icone-vide{font-size:60px; margin-bottom:20px;}
  .panier-vide p{font-size:16px; margin-bottom:20px;}
  .panier-vide a{color:var(--rose); font-size:15px;}

  .panier-layout{display:flex; gap:40px; flex-wrap:wrap; padding-bottom:90px;}
  .panier-liste{flex:2; min-width:320px;}
  .panier-resume{flex:1; min-width:280px; background:var(--carte); border:1px solid var(--bordure); border-radius:14px; padding:26px; align-self:flex-start; position:sticky; top:90px;}

  .article{display:flex; gap:18px; padding:20px 0; border-bottom:1px solid var(--bordure); align-items:center; transition:all .3s;}
  .article .img{width:100px; height:100px; border-radius:10px; background:var(--noir-clair); display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; color:var(--gris); font-size:11px; position:relative;}
  .article .img img{width:100%; height:100%; object-fit:cover;}
  .article .infos{flex:1;}
  .article .infos h3{margin:0 0 4px; font-size:16px; font-weight:500; font-family:'Playfair Display',serif;}
  .article .infos .details{color:var(--gris); font-size:12px; margin-bottom:4px;}
  .article .infos .prix-unite{color:var(--gris); font-size:13px;}
  .article .quantite-form{display:flex; align-items:center; gap:8px;}
  .article .quantite-form button{width:32px; height:32px; border-radius:50%; border:1px solid var(--bordure); background:transparent; color:var(--blanc); cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; transition:all .2s;}
  .article .quantite-form button:hover{border-color:var(--or); color:var(--or);}
  .article .quantite-form input{width:50px; padding:8px; background:var(--noir-clair); border:1px solid var(--bordure); border-radius:8px; color:var(--blanc); text-align:center; font-size:14px;}
  .article .sous-total{width:110px; text-align:right; color:var(--or); font-weight:600; font-size:16px;}
  .article .supprimer{color:#e07b73; font-size:13px; margin-left:10px; cursor:pointer; background:none; border:none; font-family:'Poppins',sans-serif; transition:color .2s;}
  .article .supprimer:hover{color:#ff4444;}

  .resume-ligne{display:flex; justify-content:space-between; margin-bottom:14px; font-size:14px; color:var(--gris);}
  .resume-total{display:flex; justify-content:space-between; font-size:20px; font-weight:600; color:var(--blanc); padding-top:16px; border-top:1px solid var(--bordure); margin-bottom:24px;}
  .resume-total span:last-child{color:var(--or);}
  .btn-commander{display:block; width:100%; text-align:center; padding:16px; background:var(--or); color:var(--blanc); border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; text-transform:uppercase; letter-spacing:1px; transition:all .2s; font-family:'Poppins',sans-serif; text-decoration:none;}
  .btn-commander:hover{background:#e6c200; transform:translateY(-2px);}
  .btn-commander:disabled{background:var(--gris); cursor:not-allowed; transform:none; opacity:0.6;}

  .reseaux-sociaux{display:flex; gap:12px; align-items:center;}
  .btn-social{padding:8px 16px; border-radius:25px; font-size:13px; text-decoration:none; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Poppins',sans-serif; font-weight:500;}
  .btn-whatsapp{background:#25D366; color:#fff;}
  .btn-whatsapp:hover{background:#1fb855; transform:translateY(-2px);}
  .btn-instagram{background:linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color:#fff;}
  .btn-instagram:hover{transform:translateY(-2px); opacity:0.9;}

  /* Toast notification */
  .toast{
    position:fixed; bottom:24px; right:24px; background:var(--carte); border:1px solid var(--rose);
    color:var(--blanc); padding:14px 22px; border-radius:10px; font-size:14px;
    opacity:0; transform:translateY(10px); transition:.25s; pointer-events:none; z-index:1000;
  }
  .toast.show{opacity:1; transform:translateY(0);}
  .toast.success{border-color:#4CAF50;}
  .toast.error{border-color:#e07b73;}

  @media (max-width: 860px){
    .page-titre{padding:40px 20px 20px;}
    .page-titre h1{font-size:28px;}
    .panier-layout{flex-direction:column-reverse; gap:24px; padding-bottom:60px;}
    .panier-resume{position:static; padding:20px;}
  }
  @media (max-width: 640px){
    .article{
      flex-wrap:wrap; position:relative; gap:12px 14px; padding:16px 0;
    }
    .article .img{width:76px; height:76px;}
    .article .infos{flex:1 1 auto; min-width:0;}
    .article .infos h3{font-size:14px;}
    .article .quantite-form{order:3; flex:1 0 auto;}
    .article .sous-total{order:4; text-align:right; width:auto; margin-left:auto; font-size:15px;}
    .article .supprimer{position:absolute; top:16px; right:0; margin:0; font-size:12px;}
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="nav-wrap">
    <a href="index.php" class="logo">Betty<span>_</span>Mode</a>
    <nav class="nav-links" id="navLinks">
      <a href="index.php">Accueil</a>
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

<div class="page-titre"><h1>Mon panier</h1></div>

<div class="conteneur">
  <!-- Panier vide -->
  <div class="panier-vide" id="panierVide">
    <div class="icone-vide">🛒</div>
    <p>Votre panier est vide pour le moment.</p>
    <a href="catalogue.php">→ Découvrir notre catalogue</a>
  </div>

  <!-- Panier avec articles -->
  <div class="panier-layout" id="panierLayout">
    <div class="panier-liste" id="panierListe">
      <!-- Les articles seront générés dynamiquement -->
    </div>

    <div class="panier-resume">
      <h3 style="margin-top:0; margin-bottom:20px; font-family:'Playfair Display',serif;">Résumé</h3>
      <div class="resume-ligne"><span>Sous-total</span><span id="sousTotal">0,00 DH</span></div>
      <div class="resume-ligne"><span>Livraison</span><span id="fraisLivraison">Offerite 🎁</span></div>
      <div class="resume-total"><span>Total</span><span id="total">0,00 DH</span></div>
      <button class="btn-commander" id="btnCommander" onclick="passerCommande()">Passer la commande</button>
      <p style="text-align:center; font-size:12px; color:var(--gris); margin-top:12px;">
        💵 Paiement à la livraison
      </p>
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

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<script>
  // ============================================================
  // DONNÉES PRODUITS (passées depuis PHP)
  // ============================================================
  const produitsRef = <?= json_encode($produitsMap) ?>;

  // ============================================================
  // MENU BURGER
  // ============================================================
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

  // ============================================================
  // FONCTIONS UTILITAIRES
  // ============================================================
  function formaterPrix(v){
    return v.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DH';
  }

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
  // GESTION DU PANIER
  // ============================================================
  function chargerPanier() {
    const panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    const panierListe = document.getElementById('panierListe');
    panierListe.innerHTML = '';

    if (panier.length === 0) {
      document.getElementById('panierVide').style.display = 'block';
      document.getElementById('panierLayout').style.display = 'none';
      document.getElementById('badgePanier').textContent = '0';
      return;
    }

    document.getElementById('panierVide').style.display = 'none';
    document.getElementById('panierLayout').style.display = 'flex';

    panier.forEach((article, index) => {
      const ref = produitsRef[article.id] || { 
        nom: article.nom || 'Produit', 
        prix: article.prix || 0, 
        image: article.image || '' 
      };
      
      const div = document.createElement('div');
      div.className = 'article';
      div.dataset.index = index;
      div.dataset.prix = ref.prix;
      div.dataset.id = article.id;
      
      // Gérer l'image avec fallback
      const imageHtml = ref.image 
        ? `<img src="${ref.image}" alt="${ref.nom}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">`
        : '';
      
      div.innerHTML = `
        <div class="img">
          ${imageHtml}
          <span class="placeholder" style="display:none; position:absolute; color:var(--gris); font-size:10px;">Photo</span>
        </div>
        <div class="infos">
          <h3>${ref.nom}</h3>
          <div class="details">Taille : ${article.taille || 'N/A'} ${article.couleur && article.couleur !== 'N/A' ? '· Couleur : ' + article.couleur : ''}</div>
          <div class="prix-unite">${formaterPrix(ref.prix)} / unité</div>
        </div>
        <div class="quantite-form">
          <button onclick="changerQuantite(${index}, -1)">−</button>
          <input type="number" value="${article.quantite}" min="1" max="20" onchange="majQuantite(${index}, this.value)">
          <button onclick="changerQuantite(${index}, 1)">+</button>
        </div>
        <div class="sous-total">${formaterPrix(ref.prix * article.quantite)}</div>
        <button class="supprimer" onclick="retirerArticle(${index})">🗑 Retirer</button>
      `;
      panierListe.appendChild(div);
    });

    recalculer();
  }

  function changerQuantite(index, delta) {
    let panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    if (panier[index]) {
      panier[index].quantite = Math.max(1, Math.min(20, panier[index].quantite + delta));
      localStorage.setItem('panierBetty', JSON.stringify(panier));
      chargerPanier();
    }
  }

  function majQuantite(index, valeur) {
    let panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    const qte = parseInt(valeur);
    if (panier[index] && qte > 0 && qte <= 20) {
      panier[index].quantite = qte;
      localStorage.setItem('panierBetty', JSON.stringify(panier));
      chargerPanier();
    }
  }

  function retirerArticle(index) {
    let panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    panier.splice(index, 1);
    localStorage.setItem('panierBetty', JSON.stringify(panier));
    chargerPanier();
    afficherToast('Article retiré du panier', 'success');
  }

  function recalculer() {
    let total = 0;
    let nbArticles = 0;
    const articles = document.querySelectorAll('#panierListe .article');
    
    articles.forEach(art => {
      const prix = parseFloat(art.dataset.prix);
      const inputQte = art.querySelector('input[type=number]');
      const qte = parseInt(inputQte ? inputQte.value : 1) || 1;
      const sousTotal = prix * qte;
      const sousTotalEl = art.querySelector('.sous-total');
      if (sousTotalEl) sousTotalEl.textContent = formaterPrix(sousTotal);
      total += sousTotal;
      nbArticles += qte;
    });

    document.getElementById('sousTotal').textContent = formaterPrix(total);
    document.getElementById('total').textContent = formaterPrix(total);
    document.getElementById('badgePanier').textContent = nbArticles;
    
    // Désactiver le bouton si panier vide
    document.getElementById('btnCommander').disabled = nbArticles === 0;
  }

  // ============================================================
  // PASSER COMMANDE
  // ============================================================
  function passerCommande() {
    const panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    if (panier.length === 0) {
      afficherToast('Votre panier est vide', 'error');
      return;
    }
    
    // Récupérer le total
    const totalEl = document.getElementById('total');
    const total = parseFloat(totalEl.textContent.replace(/[^\d,]/g, '').replace(',', '.'));
    
    // Rediriger vers la page de checkout avec les données
    // On pourrait aussi ouvrir un modal ou rediriger vers checkout.php
    window.location.href = 'checkout.php';
  }

  // ============================================================
  // INITIALISATION
  // ============================================================
  chargerPanier();
</script>
</body>
</html>