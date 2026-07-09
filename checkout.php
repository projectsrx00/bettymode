<?php
// Configuration de la base de données
$host = 'localhost:3307';
$dbname = 'bettymode';
$username = 'root'; // À adapter selon votre configuration
$password = ''; // À adapter selon votre configuration

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer tous les produits pour les références
$stmt = $pdo->prepare("SELECT id, nom, prix, image FROM produits WHERE actif = 1");
$stmt->execute();
$produitsRef = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau associatif pour un accès facile
$produitsMap = [];
foreach ($produitsRef as $p) {
    $produitsMap[$p['id']] = $p;
}

// ============================================================
// TRAITEMENT DU FORMULAIRE (enregistrement en BDD)
// ============================================================
$messageConfirmation = null;
$erreur = null;
$numCommande = null;
$totalCommande = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'valider_commande') {
    try {
        // Récupérer les données du formulaire
        $nomClient = trim($_POST['nom_client'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $quartier = trim($_POST['quartier'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $modePaiement = 'À la livraison';
        
        // Récupérer le panier depuis le formulaire
        $panierJson = $_POST['panier'] ?? '[]';
        $panier = json_decode($panierJson, true);
        
        // Si le panier est vide, essayer de le récupérer depuis localStorage (via JS)
        // Dans ce cas, on utilise le panier envoyé par le formulaire
        
        if (empty($panier)) {
            throw new Exception('Votre panier est vide');
        }
        
        // Validation des champs
        if (empty($nomClient)) throw new Exception('Le nom est requis');
        if (empty($telephone)) throw new Exception('Le téléphone est requis');
        if (empty($ville)) throw new Exception('La ville est requise');
        if (empty($quartier)) throw new Exception('Le quartier est requis');
        if (empty($adresse)) throw new Exception('L\'adresse est requise');
        
        // Calculer les totaux
        $sousTotal = 0;
        $articles = [];
        foreach ($panier as $item) {
            $produitId = $item['id'];
            $quantite = $item['quantite'];
            $prix = floatval($item['prix'] ?? 0);
            
            // Si le produit existe dans la base, on prend le prix à jour
            if (isset($produitsMap[$produitId])) {
                $prix = floatval($produitsMap[$produitId]['prix']);
            }
            
            $sousTotal += $prix * $quantite;
            $articles[] = [
                'produit_id' => $produitId,
                'taille' => $item['taille'] ?? null,
                'couleur' => $item['couleur'] ?? null,
                'quantite' => $quantite,
                'prix_unitaire' => $prix
            ];
        }
        
        $fraisLivraison = 0; // Livraison offerte
        $total = $sousTotal + $fraisLivraison;
        $totalCommande = $total;
        
        // Générer un numéro de commande unique
        $numero = 'BM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Démarrer la transaction
        $pdo->beginTransaction();
        
        // 1. Insérer la commande
        $stmt = $pdo->prepare("
            INSERT INTO commandes (
                numero, nom_client, telephone, email, ville, quartier, adresse,
                mode_paiement, sous_total, frais_livraison, total, statut, date_commande
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en-attente', NOW()
            )
        ");
        
        $stmt->execute([
            $numero,
            $nomClient,
            $telephone,
            $email ?: null,
            $ville,
            $quartier,
            $adresse,
            $modePaiement,
            $sousTotal,
            $fraisLivraison,
            $total
        ]);
        
        $commandeId = $pdo->lastInsertId();
        
        // 2. Insérer les articles de la commande
        $stmt = $pdo->prepare("
            INSERT INTO commande_articles (
                commande_id, produit_id, taille, couleur, quantite, prix_unitaire
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($articles as $article) {
            $stmt->execute([
                $commandeId,
                $article['produit_id'],
                $article['taille'],
                $article['couleur'],
                $article['quantite'],
                $article['prix_unitaire']
            ]);
        }
        
        // 3. Mettre à jour le stock des produits
        $stmtStock = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");
        foreach ($articles as $article) {
            $stmtStock->execute([
                $article['quantite'],
                $article['produit_id'],
                $article['quantite']
            ]);
        }
        
        // Valider la transaction
        $pdo->commit();
        
        $numCommande = $numero;
        $messageConfirmation = "Votre commande a été enregistrée avec succès !";
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($pdo->inTransaction()) {
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
<title>Checkout — Betty Mode</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .page-titre{padding:60px 24px 30px; text-align:center;}
  .page-titre h1{font-size:36px; margin:0;}
  .page-titre p{color:var(--gris); margin:8px 0 0;}

  .checkout-layout{display:flex; gap:40px; flex-wrap:wrap; padding-bottom:90px;}
  .checkout-form{flex:2; min-width:320px;}
  .checkout-resume{flex:1; min-width:280px; background:var(--carte); border:1px solid var(--bordure); border-radius:14px; padding:26px; align-self:flex-start; position:sticky; top:90px;}

  .etape-indicator{display:flex; gap:20px; margin-bottom:30px;}
  .etape{display:flex; align-items:center; gap:8px; color:var(--gris); font-size:13px;}
  .etape .numero{width:28px; height:28px; border-radius:50%; border:1px solid var(--bordure); display:flex; align-items:center; justify-content:center; font-size:13px;}
  .etape.active{color:var(--blanc);}
  .etape.active .numero{background:var(--or); color:var(--blanc); border-color:var(--or);}
  .etape.complete{color:var(--or);}
  .etape.complete .numero{background:var(--or); color:var(--blanc); border-color:var(--or);}

  .form-section{background:var(--carte); border:1px solid var(--bordure); border-radius:14px; padding:24px; margin-bottom:20px;}
  .form-section h3{font-family:'Playfair Display',serif; font-size:20px; margin:0 0 20px; padding-bottom:12px; border-bottom:1px solid var(--bordure);}
  .form-row{display:flex; gap:14px; flex-wrap:wrap; margin-bottom:16px;}
  .form-group{flex:1; min-width:200px;}
  .form-group label{display:block; font-size:13px; color:var(--gris); margin-bottom:6px; text-transform:uppercase; letter-spacing:.5px;}
  .form-group input, .form-group select, .form-group textarea{width:100%; padding:12px; background:var(--noir-clair); border:1px solid var(--bordure); border-radius:8px; color:var(--blanc); font-family:'Poppins',sans-serif; font-size:14px; box-sizing:border-box;}
  .form-group textarea{resize:vertical; min-height:80px;}
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus{outline:none; border-color:var(--or);}

  .paiement-methode{display:flex; gap:12px; margin-top:8px; flex-wrap:wrap;}
  .methode-btn{flex:1; min-width:120px; padding:16px; border:2px solid var(--bordure); border-radius:10px; background:transparent; color:var(--blanc); cursor:pointer; text-align:center; transition:all .2s; font-family:'Poppins',sans-serif;}
  .methode-btn:hover{border-color:var(--or);}
  .methode-btn.active{border-color:var(--or); background:rgba(203,161,53,.1);}
  .methode-btn .icone{font-size:24px; display:block; margin-bottom:6px;}

  .resume-article{display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--bordure); font-size:13px;}
  .resume-article:last-child{border-bottom:none;}
  .resume-article .nom{flex:1;}
  .resume-article .details{color:var(--gris); font-size:11px;}
  .resume-article .total-article{color:var(--or); font-weight:600; margin-left:12px;}
  .resume-ligne{display:flex; justify-content:space-between; margin-bottom:14px; font-size:14px; color:var(--gris);}
  .resume-total{display:flex; justify-content:space-between; font-size:20px; font-weight:600; color:var(--blanc); padding-top:16px; border-top:1px solid var(--bordure); margin-bottom:24px;}
  .resume-total span:last-child{color:var(--or);}

  .btn-valider{display:block; width:100%; text-align:center; padding:16px; background:var(--or); color:var(--blanc); border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; text-transform:uppercase; letter-spacing:1px; transition:all .2s; font-family:'Poppins',sans-serif;}
  .btn-valider:hover{background:#e6c200; transform:translateY(-2px);}
  .btn-valider:disabled{background:var(--gris); cursor:not-allowed; transform:none;}

  .message-confirmation{display:block; text-align:center; padding:60px 24px; background:var(--carte); border:1px solid var(--bordure); border-radius:14px; margin:20px 0;}
  .message-confirmation .icone{font-size:70px; margin-bottom:20px;}
  .message-confirmation h2{font-family:'Playfair Display',serif; font-size:28px; margin:0 0 10px;}
  .message-confirmation p{color:var(--gris); margin-bottom:20px;}
  .message-confirmation .numero-commande{background:var(--noir-clair); padding:10px 20px; border-radius:8px; display:inline-block; font-weight:600; color:var(--or);}
  .message-confirmation .btn-retour{display:inline-block; margin-top:20px; padding:14px 30px; background:var(--or); color:var(--blanc); text-decoration:none; border-radius:8px; font-weight:600; transition:all .2s;}
  .message-confirmation .btn-retour:hover{background:#e6c200; transform:translateY(-2px);}

  .error-message{color:#e07b73; font-size:12px; margin-top:4px; display:none;}
  .error-global{background:rgba(224,123,115,0.1); border:1px solid #e07b73; color:#e07b73; padding:12px 16px; border-radius:8px; margin-bottom:16px;}

  .btn-social{padding:8px 16px; border-radius:25px; font-size:13px; text-decoration:none; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Poppins',sans-serif; font-weight:500;}
  .btn-whatsapp{background:#25D366; color:#fff;}
  .btn-whatsapp:hover{background:#1fb855; transform:translateY(-2px);}
  .btn-instagram{background:linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color:#fff;}
  .btn-instagram:hover{transform:translateY(-2px); opacity:0.9;}
  .reseaux-sociaux{display:flex; gap:12px; align-items:center;}

  .hidden { display: none !important; }

  @media (max-width: 768px){
    .page-titre{padding:40px 20px 20px;}
    .page-titre h1{font-size:26px;}
    .checkout-layout{flex-direction:column-reverse; gap:24px; padding-bottom:60px;}
    .checkout-resume{position:static; padding:20px;}
    .etape-indicator{gap:10px; flex-wrap:wrap; margin-bottom:22px;}
    .etape{font-size:12px;}
    .form-section{padding:18px;}
    .form-section h3{font-size:17px;}
    .form-row{gap:12px; margin-bottom:12px;}
    .form-group{min-width:100%;}
    .paiement-methode{flex-direction:column;}
    .methode-btn{min-width:100%;}
    .message-confirmation{padding:40px 20px;}
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
  <h1>Finaliser la commande</h1>
  <p>Paiement à la livraison · Cash on delivery</p>
</div>

<div class="conteneur">
  
  <?php if ($messageConfirmation): ?>
    <!-- ====== MESSAGE DE CONFIRMATION ====== -->
    <div class="message-confirmation">
      <div class="icone">✅</div>
      <h2>Commande confirmée !</h2>
      <p>Votre commande a bien été enregistrée. Betty vous contactera pour confirmer les détails de la livraison.</p>
      <div class="numero-commande">Commande #<?= htmlspecialchars($numCommande) ?></div>
      <p style="margin-top:16px; font-size:14px; color:var(--gris);">
        💵 Vous paierez <strong><?= number_format($totalCommande, 2, ',', ' ') ?> DH</strong> à la livraison.
      </p>
      <a href="index.php" class="btn-retour">Retour à l'accueil</a>
      <a href="catalogue.php" class="btn-retour" style="background:var(--carte); border:1px solid var(--or); color:var(--or); margin-left:10px;">Continuer mes achats</a>
    </div>
    
    <script>
      // Vider le panier après confirmation
      localStorage.removeItem('panierBetty');
      document.getElementById('badgePanier').textContent = '0';
    </script>
    
  <?php else: ?>
    
    <?php if ($erreur): ?>
      <div class="error-global">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- ====== FORMULAIRE CHECKOUT ====== -->
    <div class="checkout-layout">
      <div class="checkout-form">
        <form method="POST" action="checkout.php" id="checkoutForm" onsubmit="return preparerFormulaire()">
          <input type="hidden" name="action" value="valider_commande">
          <input type="hidden" name="panier" id="panierHidden" value="">

          <!-- Étapes -->
          <div class="etape-indicator">
            <div class="etape active"><span class="numero">1</span> Livraison</div>
            <div class="etape"><span class="numero">2</span> Contact</div>
            <div class="etape"><span class="numero">3</span> Confirmation</div>
          </div>

          <!-- Étape 1 : Adresse de livraison -->
          <div class="form-section">
            <h3>📍 Adresse de livraison</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Ville *</label>
                <select name="ville" id="ville" required>
                  <option value="">Sélectionnez votre ville</option>
                  <option value="Rabat">Rabat</option>
                  <option value="Casablanca">Casablanca</option>
                  <option value="Marrakech">Marrakech</option>
                  <option value="Fès">Fès</option>
                  <option value="Tanger">Tanger</option>
                  <option value="Agadir">Agadir</option>
                  <option value="Meknès">Meknès</option>
                  <option value="Oujda">Oujda</option>
                  <option value="Kenitra">Kenitra</option>
                  <option value="Tétouan">Tétouan</option>
                  <option value="Autre">Autre</option>
                </select>
                <div class="error-message" id="errorVille">Veuillez sélectionner une ville</div>
              </div>
              <div class="form-group">
                <label>Quartier / Arrondissement *</label>
                <input type="text" name="quartier" id="quartier" placeholder="Ex : Agdal, Maârif..." required>
                <div class="error-message" id="errorQuartier">Ce champ est requis</div>
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label>Adresse complète *</label>
              <textarea name="adresse" id="adresse" placeholder="Rue, numéro d'immeuble, étage, indications supplémentaires..." required></textarea>
              <div class="error-message" id="errorAdresse">Veuillez entrer votre adresse</div>
            </div>
          </div>

          <!-- Étape 2 : Contact -->
          <div class="form-section">
            <h3>📞 Informations de contact</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Nom complet *</label>
                <input type="text" name="nom_client" id="nomClient" placeholder="Votre nom et prénom" required>
                <div class="error-message" id="errorNom">Ce champ est requis</div>
              </div>
              <div class="form-group">
                <label>Téléphone *</label>
                <input type="tel" name="telephone" id="telephone" placeholder="+212 6XX XXX XXX" required>
                <div class="error-message" id="errorTelephone">Numéro de téléphone requis</div>
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label>Email (optionnel)</label>
              <input type="email" name="email" id="email" placeholder="votre@email.com">
            </div>
          </div>

          <!-- Étape 3 : Paiement -->
          <div class="form-section">
            <h3>💳 Mode de paiement</h3>
            <p style="color:var(--gris); font-size:14px; margin-bottom:16px;">Sélectionnez votre méthode de paiement :</p>
            <div class="paiement-methode">
              <button type="button" class="methode-btn active">
                <span class="icone">💵</span>
                Paiement à la livraison
              </button>
            </div>
            <p style="color:var(--gris); font-size:12px; margin-top:8px;">
              ✅ Paiement en espèces à la réception de votre commande
            </p>
          </div>

          <button type="submit" class="btn-valider">
            ✅ Confirmer la commande
          </button>
        </form>
      </div>

      <!-- Résumé de la commande -->
      <div class="checkout-resume">
        <h3 style="margin-top:0; margin-bottom:20px; font-family:'Playfair Display',serif;">Votre commande</h3>
        <div id="resumeArticles"></div>
        <div class="resume-ligne" style="margin-top:12px;"><span>Sous-total</span><span id="sousTotal">0,00 DH</span></div>
        <div class="resume-ligne"><span>Livraison</span><span style="color:#4CAF50;">Offerite 🎁</span></div>
        <div class="resume-total"><span>Total à payer</span><span id="total">0,00 DH</span></div>
        <p style="text-align:center; font-size:12px; color:var(--gris); margin-top:12px;">
          💵 Vous paierez à la réception de votre commande
        </p>
      </div>
    </div>
  <?php endif; ?>
</div>

<footer>
  <div class="logo" style="font-size:20px; color:var(--blanc); margin-bottom:8px;">Betty<span style="color:var(--rose);">_</span>Mode</div>
  <p>Fashion designer · Maroc</p>
  <div style="display:flex; justify-content:center; gap:16px; margin:16px 0; flex-wrap:wrap;">
    <a href="https://wa.me/212721645985" target="_blank" class="btn-social btn-whatsapp">💬 WhatsApp</a>
    <a href="https://instagram.com/betty_.mode" target="_blank" class="btn-social btn-instagram">📷 Instagram</a>
  </div>
  <p>&copy; 2026 Betty Mode — Tous droits réservés</p>
</footer>

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

  // ============================================================
  // CHARGEMENT DU RÉSUMÉ
  // ============================================================
  function chargerResume() {
    const panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    const resumeContainer = document.getElementById('resumeArticles');
    let total = 0;
    let nbArticles = 0;
    
    if (!resumeContainer) return;
    
    resumeContainer.innerHTML = '';
    
    if (panier.length === 0) {
      // Rediriger vers le panier si vide
      window.location.href = 'panier.php';
      return;
    }

    panier.forEach(article => {
      const ref = produitsRef[article.id] || { nom: article.nom || 'Produit', prix: article.prix || 0 };
      const sousTotal = ref.prix * article.quantite;
      total += sousTotal;
      nbArticles += article.quantite;

      const div = document.createElement('div');
      div.className = 'resume-article';
      div.innerHTML = `
        <div class="nom">
          ${ref.nom}
          <div class="details">Taille: ${article.taille || 'N/A'} · Qté: ${article.quantite} ${article.couleur && article.couleur !== 'N/A' ? '· ' + article.couleur : ''}</div>
        </div>
        <div class="total-article">${formaterPrix(sousTotal)}</div>
      `;
      resumeContainer.appendChild(div);
    });

    document.getElementById('sousTotal').textContent = formaterPrix(total);
    document.getElementById('total').textContent = formaterPrix(total);
    document.getElementById('badgePanier').textContent = nbArticles;
    
    // Stocker le total pour l'affichage dans la confirmation
    window.totalCommande = total;
  }

  // ============================================================
  // PRÉPARATION DU FORMULAIRE (avant soumission)
  // ============================================================
  function preparerFormulaire() {
    let valide = true;

    // Récupération des valeurs
    const ville = document.getElementById('ville').value;
    const quartier = document.getElementById('quartier').value.trim();
    const adresse = document.getElementById('adresse').value.trim();
    const nomClient = document.getElementById('nomClient').value.trim();
    const telephone = document.getElementById('telephone').value.trim();

    // Reset erreurs
    document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');

    // Validation
    if (!ville) { document.getElementById('errorVille').style.display = 'block'; valide = false; }
    if (!quartier) { document.getElementById('errorQuartier').style.display = 'block'; valide = false; }
    if (!adresse) { document.getElementById('errorAdresse').style.display = 'block'; valide = false; }
    if (!nomClient) { document.getElementById('errorNom').style.display = 'block'; valide = false; }
    if (!telephone) { document.getElementById('errorTelephone').style.display = 'block'; valide = false; }

    if (!valide) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return false;
    }

    // Vérifier que le panier n'est pas vide
    const panier = JSON.parse(localStorage.getItem('panierBetty') || '[]');
    if (panier.length === 0) {
      alert('Votre panier est vide');
      return false;
    }

    // Ajouter le panier dans un champ caché
    document.getElementById('panierHidden').value = JSON.stringify(panier);
    
    return true;
  }

  // ============================================================
  // INITIALISATION
  // ============================================================
  // Vérifier si on est en mode confirmation (le panier a déjà été vidé)
  <?php if (!$messageConfirmation): ?>
    chargerResume();
  <?php endif; ?>
  
  console.log('✅ Checkout prêt');
</script>
</body>
</html>