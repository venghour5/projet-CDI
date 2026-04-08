<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

if ((int)($_SESSION['role'] ?? -1) !== 0) {
    header("Location: cdi.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Réservation Classe mobile</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style-reservation.css" />
</head>
<body>

  <header class="site-header">
    <nav class="navbar">
      <div class="logo-lycee">
        <a href="index.php">
          <span class="logo-mark">CDI</span>
          CDI <span class="logo-separator">-</span> Lycee
        </a>
      </div>

      <ul class="nav-links">
        <li><a href="vehicule.php">Vehicule</a></li>
        <li><a href="radio.php">Salle radio</a></li>
        <li><a href="mobile.php" class="active">Classe mobile</a></li>
        <li><a href="logout.php">Deconnexion</a></li>
        <li class="admin-pill">Admin <?php echo htmlspecialchars($_SESSION['login']); ?></li>
      </ul>
    </nav>
  </header>

  <main class="page">
    <div class="reservation-layout">

      <section class="agenda-card">
        <div class="resource-tabs">
          <button class="resource-btn" type="button" onclick="window.location.href='vehicule.php'">Véhicule</button>
          <button class="resource-btn" type="button" onclick="window.location.href='radio.php'">Salle radio</button>
          <button class="resource-btn active" type="button">Classe mobile</button>
        </div>

        <h2 class="agenda-title">Planning de réservation classe mobile</h2>

        <div class="agenda-grid">
          <div class="corner"></div>
          <div class="day-header">Lundi</div>
          <div class="day-header">Mardi</div>
          <div class="day-header">Mercredi</div>
          <div class="day-header">Jeudi</div>
          <div class="day-header">Vendredi</div>
          <div class="day-header">Samedi</div>
          <div class="day-header">Dimanche</div>

          <div class="time-label">8h35</div>
          <button class="slot" data-day="Lundi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="8h35" type="button"></button>

          <div class="time-label">9h35</div>
          <button class="slot" data-day="Lundi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="9h35" type="button"></button>

          <div class="time-label">10h45</div>
          <button class="slot" data-day="Lundi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="10h45" type="button"></button>

          <div class="time-label">11h45</div>
          <button class="slot" data-day="Lundi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="11h45" type="button"></button>

          <div class="time-label">13h15</div>
          <button class="slot" data-day="Lundi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="13h15" type="button"></button>

          <div class="time-label">14h15</div>
          <button class="slot" data-day="Lundi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="14h15" type="button"></button>

          <div class="time-label">15h25</div>
          <button class="slot" data-day="Lundi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="15h25" type="button"></button>

          <div class="time-label">16h25</div>
          <button class="slot" data-day="Lundi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="16h25" type="button"></button>

          <div class="time-label">17h20</div>
          <button class="slot" data-day="Lundi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Samedi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Dimanche" data-time="17h20" type="button"></button>
        </div>
      </section>

      <aside class="history-card">
        <h2 class="history-title">Historique</h2>
        <p class="history-subtitle">Dernières réservations classe mobile</p>

        <div class="history-list" id="historyList">
          <p class="history-empty" id="historyEmpty">Aucune réservation pour le moment.</p>
        </div>

        <div class="legend-box">
          <h3 class="legend-title">Professeurs et couleurs</h3>
          <div class="legend-list" id="legendList">
            <p class="legend-empty">Aucun professeur enregistré.</p>
          </div>
        </div>
      </aside>

    </div>
  </main>

  <div class="modal-overlay" id="reservationModal">
    <div class="modal-box">
      <h2>Nouvelle réservation</h2>

      <p class="modal-info" id="selectedSlotInfo">Créneau sélectionné :</p>

      <div class="modal-group">
        <label for="teacherName">Nom du professeur</label>
        <input type="text" id="teacherName" placeholder="Ex : Mme Dupont">
      </div>

      <div class="modal-group">
        <label for="durationSelect">Durée de réservation</label>
        <select id="durationSelect">
          <option value="1">1 heure</option>
          <option value="2">2 heures</option>
          <option value="3">3 heures</option>
          <option value="4">4 heures</option>
        </select>
      </div>

      <div class="modal-actions">
        <button type="button" class="modal-btn cancel-btn" id="cancelReservation">Annuler</button>
        <button type="button" class="modal-btn confirm-btn" id="confirmReservation">Valider</button>
      </div>
    </div>
  </div>

  <script>
    const resourceName = "Classe mobile";
    const slots = document.querySelectorAll(".slot");
    const historyList = document.getElementById("historyList");
    const historyEmpty = document.getElementById("historyEmpty");
    const legendList = document.getElementById("legendList");

    const reservationModal = document.getElementById("reservationModal");
    const selectedSlotInfo = document.getElementById("selectedSlotInfo");
    const teacherNameInput = document.getElementById("teacherName");
    const durationSelect = document.getElementById("durationSelect");
    const cancelReservation = document.getElementById("cancelReservation");
    const confirmReservation = document.getElementById("confirmReservation");

    const times = ["8h35", "9h35", "10h45", "11h45", "13h15", "14h15", "15h25", "16h25", "17h20"];

    const teacherColors = {};
    const colorPalette = [
      "#e74c3c", "#3498db", "#27ae60", "#f39c12",
      "#9b59b6", "#1abc9c", "#e67e22", "#2ecc71",
      "#34495e", "#d35400", "#8e44ad", "#16a085"
    ];

    let colorIndex = 0;
    let selectedSlot = null;

    function getTeacherColor(name) {
      if (!teacherColors[name]) {
        teacherColors[name] = colorPalette[colorIndex % colorPalette.length];
        colorIndex++;
        renderLegend();
      }
      return teacherColors[name];
    }

    function renderLegend() {
      const names = Object.keys(teacherColors);

      if (names.length === 0) {
        legendList.innerHTML = '<p class="legend-empty">Aucun professeur enregistré.</p>';
        return;
      }

      legendList.innerHTML = "";

      names.forEach(name => {
        const item = document.createElement("div");
        item.className = "legend-item";
        item.innerHTML = `
          <span class="legend-color" style="background-color: ${teacherColors[name]};"></span>
          <span class="legend-name">${name}</span>
        `;
        legendList.appendChild(item);
      });
    }

    slots.forEach(slot => {
      slot.addEventListener("click", () => {
        if (slot.classList.contains("reserved")) {
          alert("Ce créneau est déjà réservé.");
          return;
        }

        selectedSlot = slot;
        teacherNameInput.value = "";
        durationSelect.value = "1";
        selectedSlotInfo.textContent = `Créneau sélectionné : ${slot.dataset.day} à ${slot.dataset.time}`;
        reservationModal.classList.add("active");
      });
    });

    cancelReservation.addEventListener("click", () => {
      reservationModal.classList.remove("active");
      selectedSlot = null;
    });

    confirmReservation.addEventListener("click", () => {
      if (!selectedSlot) return;

      const teacherName = teacherNameInput.value.trim();
      const duration = parseInt(durationSelect.value, 10);

      if (!teacherName) {
        alert("Veuillez saisir le nom du professeur.");
        return;
      }

      const day = selectedSlot.dataset.day;
      const startTime = selectedSlot.dataset.time;
      const startIndex = times.indexOf(startTime);
      const endIndex = startIndex + duration - 1;

      if (endIndex >= times.length) {
        alert("La réservation dépasse la fin de la journée.");
        return;
      }

      const daySlots = [...document.querySelectorAll(`.slot[data-day="${day}"]`)];

      for (let i = startIndex; i <= endIndex; i++) {
        if (daySlots[i].classList.contains("reserved")) {
          alert("Un des créneaux est déjà réservé.");
          return;
        }
      }

      const teacherColor = getTeacherColor(teacherName);

      for (let i = startIndex; i <= endIndex; i++) {
        daySlots[i].classList.add("reserved");
        daySlots[i].style.backgroundColor = teacherColor;
        daySlots[i].style.color = "#fff";
        daySlots[i].textContent = i === startIndex ? teacherName : "";
      }

      const endTime = times[endIndex];

      if (historyEmpty) {
        historyEmpty.remove();
      }

      const item = document.createElement("div");
      item.className = "history-item";
      item.innerHTML = `
        <strong>${day}</strong>
        <span>${resourceName} réservé de ${startTime} à ${endTime}</span>
        <span>Professeur : ${teacherName}</span>
        <span>Durée : ${duration} heure(s)</span>
      `;

      historyList.prepend(item);

      reservationModal.classList.remove("active");
      selectedSlot = null;
    });

    reservationModal.addEventListener("click", (e) => {
      if (e.target === reservationModal) {
        reservationModal.classList.remove("active");
        selectedSlot = null;
      }
    });
  </script>

</body>
</html>


