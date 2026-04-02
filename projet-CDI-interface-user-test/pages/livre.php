<?php
require_once '../connexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$livre = null;

if ($id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM livres WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $livre = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bienvenue au CDI</title>
  <link rel="stylesheet" href="../styles/style_test_de_recherche.css">
  <link rel="icon" type="image/png" href="../Images/cropped-Lycee-Lavoisier-Meru_Plan-de-travail-1-copie-4.png">
</head>
<body>
<header>
    <nav>
    <div class="navbar">
      <div class="logo-lycee">
        <a href="index.html">
          <img src="../Images/logo L.png" alt="logo du lycée"> 
          CDI <span>-</span> Lycée
          
        </a>
      </div>
      <ul class="nav-links">
        <div class="group">
  <svg viewBox="0 0 24 24" aria-hidden="true" class="search-icon">
    <g>
      <path
        d="M21.53 20.47l-3.66-3.66C19.195 15.24 20 13.214 20 11c0-4.97-4.03-9-9-9s-9 4.03-9 9 4.03 9 9 9c2.215 0 4.24-.804 5.808-2.13l3.66 3.66c.147.146.34.22.53.22s.385-.073.53-.22c.295-.293.295-.767.002-1.06zM3.5 11c0-4.135 3.365-7.5 7.5-7.5s7.5 3.365 7.5 7.5-3.365 7.5-7.5 7.5-7.5-3.365-7.5-7.5z"></path>
    </g>
  </svg>
    <input id="query" class="input" type="search" placeholder="Rechercher..." name="searchbar" autocomplete="off" />
    <div id="search-results">
      <div class="result-header">Résultats</div>
      <div id="results-list"></div>
    </div>
    </div>
        <li><a href="../pages/Espaces livres.html">Espaces Livres</a></li>
        <li><a href="../pages/Ressources.html">Ressources</a></li>
        <li><a href="./Recherche.html">Recherche</a></li>
        <li><a href="#">Accéder aux réservation</a></li>
      </nav>
    </div>
        </ul>
      </div>
  </header>

<div class="breadcrumb">
  <a href="recherche.html">Recherche</a>
  <span>›</span>
  <span id="bc-categorie">—</span>
  <span>›</span>
  <span id="bc-titre">—</span>
</div>

<div class="livre-page">

  <!-- COLONNE DE GAUCHE -->
  <div class="livre-left">
    <div class="cover-wrap" id="cover-wrap">📖</div>

    <div id="dispo-badge" class="dispo-badge dispo">
      <span class="dot"></span>
      <span id="dispo-text">Disponible</span>
    </div>

    <div class="cote-box">
      📍 Cote de rangement
      <strong id="cote-val">—</strong>
    </div>

    <div class="actions-box">
      <h3>Actions</h3>
      <div class="action-item" id="btn-localiser">
        <span class="icon">🗺️</span> Localiser le livre
      </div>
      <div class="action-item" id="btn-numero">
        <span class="icon">🔢</span> Numéro du livre
      </div>
      <div class="action-item" id="btn-exporter">
        <span class="icon">📥</span> Importer / Exporter
      </div>
      <div class="action-item" id="btn-envoyer">
        <span class="icon">✉️</span> Envoyer
      </div>
      <div class="action-item" id="btn-citer">
        <span class="icon">💬</span> Citer ce livre
      </div>
      <div class="action-item" id="btn-selection">
        <span class="icon">⭐</span> Ajouter à la sélection
      </div>
    </div>
    <a href="recherche.html" class="btn-retour">← Retour à la recherche</a>

  </div>
  <!-- COLONNE DE DROITE -->
  <div class="livre-right">
    <div>
      <span class="livre-categorie" id="livre-categorie">—</span>
      <h1 class="livre-titre" id="livre-titre">Chargement…</h1>
      <p class="livre-auteur" id="livre-auteur">—</p>
    </div>

    <div class="synopsis-box" id="synopsis-box">—</div>

    <div>
      <h2>Informations</h2>
      <table class="infos-table">
        <tr><td>Auteur</td><td id="info-auteur">—</td></tr>
        <tr><td>Catégorie</td><td id="info-cat">—</td></tr>
        <tr><td>Cote</td><td id="info-cote">—</td></tr>
        <tr><td>Disponibilité</td><td id="info-dispo">—</td></tr>
        <tr><td>Langue</td><td>Français</td></tr>
        <tr><td>Localisation</td><td>CDI — Lycée Lavoisier, Méru</td></tr>
      </table>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

 <footer class="footer">
  <div class="container">
    <div class="row">

      <div class="footer-col">
        <h4>CDI - Lycée</h4>
      </div>

      <div class="footer-col">
        <h4>Coordonnées</h4>
        <p>LYCÉE PROFESSIONNEL LAVOISIER</p>
        <p>8 RUE JULES FERRY</p>
        <p>60110 MERU</p>
      </div>

      <div class="footer-col">
        <h4>Informations</h4>
        <ul>
          <li ><a href="#">Mentions légales</a></li>
          <li><a href="#">Mentions RGPD</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Suivez-nous</h4>
        <div class="resaux-social">
          <a href="https://www.facebook.com/profile.php?id=100086643024970&locale=fr_FR"><img src="/Images/ressaux/facebook.png" alt="Facebook"></a>
          <a href="https://www.youtube.com/channel/UCAHjFYvFlPExoVpSqldZX5A"><img src="/Images/ressaux/youtube.png" alt="Youtube"></i></a>
          <a href="https://www.instagram.com/lyceedesmetiers_lavoisier_meru/?g=5"><img src="/Images/ressaux/instagram.png" alt="Instagram"></a>
          <a href="https://www.linkedin.com/company/lyc%C3%A9e-des-m%C3%A9tiers-des-services-antoine-lavoisier/posts/?feedView=all&viewAsMember=true"><img src="/Images/ressaux/linkedin.png" alt="Linkedin"></i></a>
        </div>
      </div>

    </div>

    <p style="text-align:center; margin-top:30px;">2025 © Tous droits réservés.</p>
  </div>
</footer>

<script>
  // ============================================
  // on recupere ce qui est dans l'url
  // par exemple : ?titre=Harry+Potter&auteur=Rowling
  // ============================================

  var urlParams = new URLSearchParams(window.location.search) // URLSearchParams est une fonction qui parse les paramètres d'une URL et permet de les lire facilement

  // on lit chaque parametre un par un depuis l'URL
  var titreLivre = urlParams.get('titre');
  var auteurLivre = urlParams.get('auteur');
  var categorieLivre = urlParams.get('categorie');
  var imageLivre = urlParams.get('image');
  var coteLivre = urlParams.get('cote');
  var synopsisLivre = urlParams.get('synopsis');
  var disponibleLivre = urlParams.get('disponible');

  // si jamais un parametre est vide on met une valeur par defaut
  if (titreLivre == null) {
    titreLivre = 'Livre inconnu'
  }
  if (auteurLivre == null) {
    auteurLivre = 'Auteur inconnu'
  }
  if (categorieLivre == null) {
    categorieLivre = 'Autre'
  }
  if (imageLivre == null) {
    imageLivre = ''
  }
  if (coteLivre == null) {
    coteLivre = '—'
  }
  if (synopsisLivre == null) {
    synopsisLivre = 'Aucun synopsis disponible.'
  }

  // pour "disponible" c'est vrai sauf si on nous envoie "false"
  var estDisponible = true
  if (disponibleLivre == 'false') {
    estDisponible = false
  }


  // ============================================
  // on met le titre de la page dans l'onglet du navigateur
  // ============================================
  document.title = titreLivre + ' — CDI Lycée'


  // ============================================
  // on remplit le fil d'ariane (le chemin en haut de la page ou le livre est dans la categorie "Roman > Fantastique" par exemple)
  // ============================================
  document.getElementById('bc-categorie').textContent = categorieLivre
  document.getElementById('bc-titre').textContent = titreLivre


  // ============================================
  // on remplit les infos principales du livre
  // ============================================
  document.getElementById('livre-titre').textContent = titreLivre
  document.getElementById('livre-auteur').textContent = 'par ' + auteurLivre
  document.getElementById('livre-categorie').textContent = categorieLivre
  document.getElementById('synopsis-box').textContent = synopsisLivre
  document.getElementById('cote-val').textContent = coteLivre

  // le tableau d'infos en bas
  document.getElementById('info-auteur').textContent = auteurLivre
  document.getElementById('info-cat').textContent = categorieLivre
  document.getElementById('info-cote').textContent = coteLivre


  // ============================================
  // gestion de la couverture du livre
  // ============================================

  var coverDiv = document.getElementById('cover-wrap')

  if (imageLivre != '') {
    // si on a une image on la met
    var imgElement = document.createElement('img')
    imgElement.src = imageLivre
    imgElement.alt = titreLivre

    // si l'image ne charge pas on met un emoji a la place
    imgElement.onerror = function() {
      coverDiv.innerHTML = getEmoji(categorieLivre)
    }

    coverDiv.innerHTML = ''
    coverDiv.appendChild(imgElement)

  } else {
    // pas d'image donc on met juste un emoji selon la categorie
    coverDiv.innerHTML = getEmoji(categorieLivre)
  }

  // cette fonction renvoie un emoji selon la categorie
  function getEmoji(categorie) {
    var cat = categorie.toLowerCase()

    if (cat.includes('manga')) {
      return '📖'
    }
    if (cat.includes('roman')) {
      return '📚'
    }
    if (cat.includes('documentaire')) {
      return '🔬'
    }
    if (cat.includes('magazine')) {
      return '📰'
    }
    if (cat.includes('référence')) {
      return '📕'
    }

    // si on sait pas on met ca par defaut
    return '📗'
  }


  // ============================================
  // disponibilite du livre (dispo ou pas)
  // ============================================

  var badgeElement = document.getElementById('dispo-badge')
  var texteDispoElement = document.getElementById('dispo-text')
  var infoDispoElement = document.getElementById('info-dispo')

  if (estDisponible == true) {
    badgeElement.className = 'dispo-badge dispo'
    texteDispoElement.textContent = 'Disponible'
    infoDispoElement.textContent = '✅ Disponible en CDI'
  } else {
    badgeElement.className = 'dispo-badge indispo'
    texteDispoElement.textContent = 'Indisponible'
    infoDispoElement.textContent = '❌ Emprunté actuellement'
  }











  // ============================================
  // la petite notification qui apparait en bas (toast)
  // ============================================

  function afficherToast(message) {
    var toastElement = document.getElementById('toast')
    toastElement.textContent = message
    toastElement.className = 'toast show' // on l'affiche
    // apres 2.8 secondes on le cache
    setTimeout(function() {
      toastElement.className = 'toast'
    }, 2800)
  }


  // ============================================
  // les boutons d'actions
  // ============================================

  document.getElementById('btn-localiser').onclick = function() {
    var cote = document.getElementById('cote-val').textContent
    afficherToast('📍 Localisation : rayon ' + cote)
  }

  document.getElementById('btn-numero').onclick = function() {
    var cote = document.getElementById('cote-val').textContent
    afficherToast('📋 Numéro : ' + cote)
  }

  document.getElementById('btn-exporter').onclick = function() {
    afficherToast('📥 Export en cours…')
  }

  document.getElementById('btn-envoyer').onclick = function() {
    afficherToast('✉️ Lien copié dans le presse-papier !')
  }

  document.getElementById('btn-citer').onclick = function() {
    // on fabrique la citation avec les infos du livre
    var citation = titreLivre + ' — ' + auteurLivre + ' (CDI Lycée Lavoisier, Méru, cote : ' + coteLivre + ')'
    navigator.clipboard.writeText(citation)
    afficherToast('📋 Citation copiée : ' + citation)
  }

  document.getElementById('btn-selection').onclick = function() {
    afficherToast('✅ Ajouté à votre sélection !')
  }

</script>
</body>
</html>

<!-- let livresCDI = [];

fetch("livres.php")
  .then(res => res.json())
  .then(data => {
    livresCDI = data;
    afficherGrille('');
  }); -->