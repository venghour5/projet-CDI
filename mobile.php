<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/src/reservation_workflow.php';
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

if (!in_array((int)($_SESSION['role'] ?? -1), [1, 3, 4], true)) {
    header("Location: cdi.php");
    exit();
}

ensureReservationWorkflowSchema($pdo);
$currentRole = (int)($_SESSION['role'] ?? -1);
$currentLogin = (string)($_SESSION['login'] ?? '');
$approvedReservations = fetchApprovedReservations($pdo, 3);
$teacherNames = fetchTeacherNames($pdo);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Réservation Classe mobile</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style-reservation.css?v=20260518" />
</head>
<body>

  <header class="site-header">
    <nav class="navbar">
      <div class="logo-lycee">
        <a href="index.php">
          <span class="logo-mark">CDI</span>
          CDI <span class="logo-separator">-</span> Lycée
        </a>
      </div>

      <ul class="nav-links">
        <li><a href="vehicule.php">Véhicule</a></li>
        <li><a href="radio.php">Salle radio</a></li>
        <li><a href="mobile.php" class="active">Classe mobile</a></li>
        <?php if (in_array($currentRole, [1, 4], true)): ?>
          <li><a href="reservation_validation.php">Confirmation</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Déconnexion</a></li>
        <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
      </ul>
    </nav>
  </header>

  <main class="page">
    <div class="reservation-layout">

      <section class="agenda-card">
        <h2 class="agenda-title">Planning de réservation classe mobile</h2>
        <div class="week-nav">
          <button type="button" class="week-nav-btn" id="prevWeekBtn" aria-label="Semaine précédente" title="Semaine précédente">&larr;</button>
          <span class="week-range" id="weekRangeLabel"></span>
          <button type="button" class="week-nav-btn" id="nextWeekBtn" aria-label="Semaine suivante" title="Semaine suivante">&rarr;</button>
        </div>

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
        <button type="button" class="history-toggle-btn hidden" id="historyToggleBtn">Voir plus</button>

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
        <input type="text" id="teacherName" list="teacherSuggestionsMobile" placeholder="Ex : Mme Dupont" autocomplete="off">
        <datalist id="teacherSuggestionsMobile">
          <?php foreach ($teacherNames as $teacherName): ?>
            <option value="<?php echo htmlspecialchars((string)$teacherName); ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <div class="modal-group">
        <label for="durationSelect">Durée de réservation</label>
        <select id="durationSelect">
          <option value="1">1 heure</option>
          <option value="2">2 heures</option>
          <option value="3">3 heures</option>
          <option value="4">4 heures</option>
          <option value="5">5 heures</option>
          <option value="6">6 heures</option>
          <option value="7">7 heures</option>
          <option value="8">8 heures</option>
          <option value="9">Journée entière</option>
        </select>
      </div>

      <div class="modal-actions">
        <button type="button" class="modal-btn cancel-btn" id="cancelReservation">Annuler</button>
        <button type="button" class="modal-btn confirm-btn" id="confirmReservation">Valider</button>
      </div>
    </div>
  </div>

  <script>
    const CURRENT_ROLE = <?php echo (int)$currentRole; ?>;
    const CURRENT_LOGIN = <?php echo json_encode($currentLogin, JSON_UNESCAPED_UNICODE); ?>;
    const RESOURCE_ID = 3;
    const RESOURCE_NAME = "Classe mobile";
    const APPROVED_RESERVATIONS = <?php echo json_encode($approvedReservations, JSON_UNESCAPED_UNICODE); ?>;

    const slots = Array.from(document.querySelectorAll(".slot"));
    const agendaGrid = document.querySelector(".agenda-grid");
    const dayHeaders = Array.from(document.querySelectorAll(".agenda-grid .day-header"));
    const historyList = document.getElementById("historyList");
    const historyEmpty = document.getElementById("historyEmpty");
    const historyToggleBtn = document.getElementById("historyToggleBtn");
    const legendList = document.getElementById("legendList");
    const weekRangeLabel = document.getElementById("weekRangeLabel");
    const prevWeekBtn = document.getElementById("prevWeekBtn");
    const nextWeekBtn = document.getElementById("nextWeekBtn");

    const reservationModal = document.getElementById("reservationModal");
    const selectedSlotInfo = document.getElementById("selectedSlotInfo");
    const teacherNameInput = document.getElementById("teacherName");
    const durationSelect = document.getElementById("durationSelect");
    const cancelReservation = document.getElementById("cancelReservation");
    const confirmReservation = document.getElementById("confirmReservation");
    const isProf = CURRENT_ROLE === 3;

    const times = ["8h35", "9h35", "10h45", "11h45", "13h15", "14h15", "15h25", "16h25", "17h20"];
    const teacherColors = {};
    const colorPalette = ["#e74c3c", "#3498db", "#27ae60", "#f39c12", "#9b59b6", "#1abc9c", "#e67e22", "#2ecc71", "#34495e", "#d35400", "#8e44ad", "#16a085"];

    let colorIndex = 0;
    let selectedSlot = null;
    let currentWeekOffset = 0;
    let weekDays = [];
    let weekDayLabelByIso = {};
    let historyLoaded = false;
    let historyExpanded = false;

    function updateSelectedSlotInfoText() {
      if (!selectedSlot || !selectedSlotInfo || !durationSelect) return;
      const dayLabel = selectedSlot.dataset.dayLabel || selectedSlot.dataset.day;
      const durationValue = parseInt(durationSelect.value, 10);
      if (durationValue === times.length) {
        selectedSlotInfo.textContent = `Créneau sélectionné : ${dayLabel} de ${times[0]} à ${times[times.length - 1]}`;
      } else {
        selectedSlotInfo.textContent = `Créneau sélectionné : ${dayLabel} à ${selectedSlot.dataset.time}`;
      }
    }

    function pad2(value) {
      return String(value).padStart(2, "0");
    }

    function buildWeekDays(weekOffset = 0) {
      const names = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi"];
      const now = new Date();
      const mondayOffset = (now.getDay() + 6) % 7;
      const monday = new Date(now);
      monday.setHours(0, 0, 0, 0);
      monday.setDate(now.getDate() - mondayOffset + (weekOffset * 7));

      return names.map((name, index) => {
        const d = new Date(monday);
        d.setDate(monday.getDate() + index);
        const iso = `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
        const display = `${name} ${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}`;
        return { iso, display, date: d };
      });
    }

    function displayDayLabel(dayValue) {
      if (weekDayLabelByIso[dayValue]) {
        return weekDayLabelByIso[dayValue];
      }
      if (/^\d{4}-\d{2}-\d{2}$/.test(dayValue)) {
        const parts = dayValue.split("-");
        return `${parts[2]}/${parts[1]}`;
      }
      return dayValue;
    }

    function isPastDay(dayKey) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey)) return false;
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const dayDate = new Date(`${dayKey}T00:00:00`);
      return dayDate < today;
    }

    function updateWeekRangeLabel() {
      if (!weekRangeLabel || weekDays.length === 0) return;
      const first = weekDays[0].display.split(" ")[1];
      const last = weekDays[weekDays.length - 1].display.split(" ")[1];
      weekRangeLabel.textContent = `Semaine du ${first} au ${last}`;
    }

    function applyCalendarLayout() {
      dayHeaders.forEach((header, index) => {
        if (index < weekDays.length) {
          header.textContent = weekDays[index].display;
        } else {
          header.remove();
        }
      });

      times.forEach((time) => {
        const rowSlots = Array.from(document.querySelectorAll(`.agenda-grid .slot[data-time="${time}"]`));
        rowSlots.forEach((slot, index) => {
          if (index < weekDays.length) {
            slot.dataset.day = weekDays[index].iso;
            slot.dataset.dayLabel = weekDays[index].display;
          } else {
            slot.remove();
          }
        });
      });

      if (agendaGrid) {
        agendaGrid.style.gridTemplateColumns = "90px repeat(5, 1fr)";
      }
    }

    function getTeacherColor(teacherName) {
      if (!teacherColors[teacherName]) {
        teacherColors[teacherName] = colorPalette[colorIndex % colorPalette.length];
        colorIndex++;
      }
      return teacherColors[teacherName];
    }

    function renderLegend(activeNames = null) {
      const names = Array.isArray(activeNames) ? activeNames : Object.keys(teacherColors);
      if (names.length === 0) {
        legendList.innerHTML = '<p class="legend-empty">Aucun professeur enregistré.</p>';
        return;
      }
      legendList.innerHTML = "";
      names.forEach(name => {
        const item = document.createElement("div");
        item.className = "legend-item";
        item.innerHTML = `<span class="legend-color" style="background-color: ${teacherColors[name]};"></span><span class="legend-name">${name}</span>`;
        legendList.appendChild(item);
      });
    }

    function clearAgendaReservations() {
      slots.forEach((slot) => {
        slot.classList.remove("reserved");
        slot.style.backgroundColor = "";
        slot.style.color = "";
        slot.textContent = "";
      });
    }

    function markReservation(dayKey, startTime, duration, teacherName) {
      const startIndex = times.indexOf(startTime);
      if (startIndex < 0) return;
      const endIndex = startIndex + duration - 1;
      const daySlots = [...document.querySelectorAll(`.slot[data-day="${dayKey}"]`)];
      const teacherColor = getTeacherColor(teacherName);

      for (let i = startIndex; i <= endIndex; i++) {
        const slot = daySlots[i];
        if (!slot) continue;
        slot.classList.add("reserved");
        slot.style.backgroundColor = teacherColor;
        slot.style.color = "#fff";
        slot.textContent = i === startIndex ? teacherName : "";
      }
    }

    function appendHistory(dayLabel, startTime, duration, teacherName, statusText, isRequest = false) {
      const startIndex = times.indexOf(startTime);
      const endTime = startIndex >= 0 && startIndex + duration - 1 < times.length ? times[startIndex + duration - 1] : startTime;
      if (historyEmpty) historyEmpty.remove();
      const item = document.createElement("div");
      item.className = "history-item";
      const actionText = isRequest ? `Demande de réservation ${RESOURCE_NAME}` : `${RESOURCE_NAME} réservé`;
      item.innerHTML = `<strong>${dayLabel}</strong><span>${actionText} de ${startTime} à ${endTime}</span><span>Professeur : ${teacherName}</span><span>Durée : ${duration} heure(s)</span><span>Statut : ${statusText}</span>`;
      historyList.prepend(item);
      updateHistoryListCompact();
    }

    function updateHistoryListCompact() {
      if (!historyList) return;
      const count = historyList.querySelectorAll(".history-item").length;
      const shouldCompact = count > 2;

      if (historyToggleBtn) {
        historyToggleBtn.classList.toggle("hidden", !shouldCompact);
        historyToggleBtn.textContent = historyExpanded ? "Voir moins" : "Voir plus";
      }

      historyList.classList.toggle("expanded", historyExpanded && shouldCompact);
      historyList.classList.toggle("compact", !historyExpanded && shouldCompact);
    }

    function renderApprovedReservationsForWeek() {
      clearAgendaReservations();
      const activeTeacherNames = new Set();
      if (!Array.isArray(APPROVED_RESERVATIONS)) { renderLegend([]); return; }
      APPROVED_RESERVATIONS.forEach((reservation) => {
        const dayKey = reservation.day || "";
        if (!weekDayLabelByIso[dayKey]) return;
        const duration = Number(reservation.duree || 1);
        const teacher = reservation.demandeur || "Utilisateur";
        markReservation(dayKey, reservation.heure_debut || "", duration, teacher);
        if (!isPastDay(dayKey)) {
          activeTeacherNames.add(teacher);
        }
      });
      renderLegend(Array.from(activeTeacherNames));
    }

    function loadHistoryOnce() {
      if (historyLoaded || !Array.isArray(APPROVED_RESERVATIONS)) return;
      APPROVED_RESERVATIONS.forEach((reservation) => {
        const dayKey = reservation.day || "";
        const dayLabel = displayDayLabel(dayKey);
        const duration = Number(reservation.duree || 1);
        const teacher = reservation.demandeur || "Utilisateur";
        appendHistory(dayLabel, reservation.heure_debut || "", duration, teacher, "Confirmée");
      });
      updateHistoryListCompact();
      historyLoaded = true;
    }

    if (historyToggleBtn) {
      historyToggleBtn.addEventListener("click", () => {
        historyExpanded = !historyExpanded;
        updateHistoryListCompact();
      });
    }

    function refreshWeek() {
      weekDays = buildWeekDays(currentWeekOffset);
      weekDayLabelByIso = Object.fromEntries(weekDays.map((d) => [d.iso, d.display]));
      updateWeekRangeLabel();
      applyCalendarLayout();
      renderApprovedReservationsForWeek();
    }

    if (isProf && confirmReservation) {
      confirmReservation.textContent = "Demander";
    }

    refreshWeek();
    loadHistoryOnce();

    if (prevWeekBtn) {
      prevWeekBtn.addEventListener("click", () => {
        currentWeekOffset -= 1;
        selectedSlot = null;
        refreshWeek();
      });
    }

    if (nextWeekBtn) {
      nextWeekBtn.addEventListener("click", () => {
        currentWeekOffset += 1;
        selectedSlot = null;
        refreshWeek();
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
        updateSelectedSlotInfoText();
        reservationModal.classList.add("active");
      });
    });

    if (durationSelect) {
      durationSelect.addEventListener("change", updateSelectedSlotInfoText);
    }

    cancelReservation.addEventListener("click", () => {
      reservationModal.classList.remove("active");
      selectedSlot = null;
    });

    confirmReservation.addEventListener("click", async () => {
      if (!selectedSlot) return;

      const teacherName = (teacherNameInput.value || "").trim();
      const duration = parseInt(durationSelect.value, 10);
      const dayKey = selectedSlot.dataset.day;
      const dayLabel = selectedSlot.dataset.dayLabel || dayKey;
      const startTime = selectedSlot.dataset.time;
      const isFullDay = duration === 9;
      const effectiveStartTime = isFullDay ? times[0] : startTime;
      const effectiveDuration = isFullDay ? times.length : duration;
      const startIndex = times.indexOf(effectiveStartTime);
      const endIndex = startIndex + effectiveDuration - 1;

      if (startIndex < 0 || endIndex >= times.length) {
        alert("La réservation dépasse la fin de la journée.");
        return;
      }
      if (!teacherName) {
        alert("Nom du professeur obligatoire.");
        return;
      }

      try {
        const response = await fetch("reservation_request.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ resource_id: RESOURCE_ID, day: dayKey, heure_debut: effectiveStartTime, duree: effectiveDuration, teacher_name: teacherName, full_day: isFullDay })
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
          alert(
            payload.error === "slot_taken"
              ? "Ce créneau est déjà réservé."
              : (payload.error === "invalid_duration"
                ? "Durée invalide pour ce créneau."
                : (payload.error === "invalid_teacher"
                  ? "Professeur invalide."
                : (CURRENT_ROLE === 3 ? "Demande impossible. Merci de réessayer." : "Réservation impossible."))
              )
          );
          return;
        }

        if (CURRENT_ROLE === 3) {
          appendHistory(dayLabel, effectiveStartTime, effectiveDuration, teacherName, "En attente de confirmation", true);
          alert("Demande envoyée. En attente de confirmation par l'admin réservation.");
        } else {
          APPROVED_RESERVATIONS.push({ day: dayKey, heure_debut: effectiveStartTime, duree: effectiveDuration, demandeur: teacherName });
          renderApprovedReservationsForWeek();
          appendHistory(dayLabel, effectiveStartTime, effectiveDuration, teacherName, "Confirmée");
        }

        reservationModal.classList.remove("active");
        selectedSlot = null;
      } catch (error) {
        alert("Erreur réseau. Merci de réessayer.");
      }
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









