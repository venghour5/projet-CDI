<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'db.php';
require_once __DIR__ . '/src/reservation_workflow.php';
require_once __DIR__ . '/src/auth_session.php';

$sessionUser = requireAuthenticatedSessionUser($pdo, [1, 3, 4], 'cdi.php');

ensureReservationWorkflowSchema($pdo);
$currentRole = (int)$sessionUser['role'];
$currentLogin = (string)$sessionUser['nom'];
$approvedReservations = fetchApprovedReservations($pdo, 1);
$teacherNames = fetchTeacherNames($pdo);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Réservation Véhicule</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style-reservation.css?v=20260520" />
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
        <?php if ($currentRole === 1): ?>
          <li><a href="index.php">Accueil</a></li>
          <li><a href="cdi.php">Zone CDI</a></li>
          <li><a href="esp.php">Modules ESP</a></li>
          <li><a href="vehicule.php" class="active">V&eacute;hicule</a></li>
          <li><a href="radio.php">Salle radio</a></li>
          <li><a href="mobile.php">Classe mobile</a></li>
          <li><a href="reservation_validation.php">Confirmation</a></li>
          <li><a href="register.php">Cr&eacute;er un compte</a></li>
          <li><a href="logout.php">D&eacute;connexion</a></li>
        <?php else: ?>
          <li><a href="vehicule.php" class="active">V&eacute;hicule</a></li>
          <li><a href="radio.php">Salle radio</a></li>
          <li><a href="mobile.php">Classe mobile</a></li>
          <?php if (in_array($currentRole, [1, 4], true)): ?>
            <li><a href="reservation_validation.php">Confirmation</a></li>
          <?php endif; ?>
          <li><a href="logout.php">D&eacute;connexion</a></li>
        <?php endif; ?>
        <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
      </ul>
    </nav>
  </header>

  <main class="page">
    <div class="reservation-layout">

      <section class="agenda-card">
        <h2 class="agenda-title">Planning de réservation véhicule</h2>

        <div class="vehicle-tabs" id="vehicleTabs">
          <button type="button" class="vehicle-btn active" data-vehicle="Renault">Renault</button>
          <button type="button" class="vehicle-btn" data-vehicle="Toyota">Toyota</button>
          <button type="button" class="vehicle-btn" data-vehicle="Peugeot">Peugeot</button>
        </div>

        <div class="week-nav">
          <button type="button" class="week-nav-btn" id="prevWeekBtn" aria-label="Semaine précédente" title="Semaine précédente">&larr;</button>
          <button type="button" class="week-nav-btn today-btn" id="todayBtn" disabled>Aujourd'hui</button>
          <span class="week-range" id="weekRangeLabel" title="Cliquer pour choisir une date"></span>
          <button type="button" class="week-nav-btn" id="nextWeekBtn" aria-label="Semaine suivante" title="Semaine suivante">&rarr;</button>
        </div>
        <div class="date-jump-popover" id="dateJumpPopover"></div>

        <div class="agenda-grid" id="agendaGrid">
          <div class="corner"></div>
          <div class="day-header">Lundi</div>
          <div class="day-header">Mardi</div>
          <div class="day-header">Mercredi</div>
          <div class="day-header">Jeudi</div>
          <div class="day-header">Vendredi</div>

          <div class="time-label">8h35</div>
          <button class="slot" data-day="Lundi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="8h35" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="8h35" type="button"></button>

          <div class="time-label">9h35</div>
          <button class="slot" data-day="Lundi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="9h35" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="9h35" type="button"></button>

          <div class="time-label">10h45</div>
          <button class="slot" data-day="Lundi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="10h45" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="10h45" type="button"></button>

          <div class="time-label">11h45</div>
          <button class="slot" data-day="Lundi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="11h45" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="11h45" type="button"></button>

          <div class="time-label">13h15</div>
          <button class="slot" data-day="Lundi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="13h15" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="13h15" type="button"></button>

          <div class="time-label">14h15</div>
          <button class="slot" data-day="Lundi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="14h15" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="14h15" type="button"></button>

          <div class="time-label">15h25</div>
          <button class="slot" data-day="Lundi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="15h25" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="15h25" type="button"></button>

          <div class="time-label">16h25</div>
          <button class="slot" data-day="Lundi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="16h25" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="16h25" type="button"></button>

          <div class="time-label">17h20</div>
          <button class="slot" data-day="Lundi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Mardi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Mercredi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Jeudi" data-time="17h20" type="button"></button>
          <button class="slot" data-day="Vendredi" data-time="17h20" type="button"></button>
        </div>
      </section>

      <aside class="history-card">
        <h2 class="history-title">Historique</h2>
        <p class="history-subtitle">Dernières réservations véhicule</p>

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

  <!-- Modale annulation (admin uniquement) -->
  <div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
      <h2>Annuler la réservation</h2>
      <p class="cancel-info" id="cancelSlotInfo"></p>
      <div class="modal-actions">
        <button type="button" class="modal-btn cancel-btn" id="cancelModalClose">Retour</button>
        <button type="button" class="modal-btn confirm-btn danger-btn" id="confirmCancelBtn">Supprimer</button>
      </div>
    </div>
  </div>

  <!-- Modale réservation -->
  <div class="modal-overlay" id="reservationModal">
    <div class="modal-box">
      <h2>Nouvelle réservation</h2>
      <p class="modal-info" id="selectedSlotInfo">Créneau sélectionné :</p>

      <div class="modal-group">
        <label for="teacherName">Nom du professeur</label>
        <input type="text" id="teacherName" list="teacherSuggestionsVehicule" placeholder="Ex : Mme Dupont" autocomplete="off">
        <datalist id="teacherSuggestionsVehicule"></datalist>
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

  <!-- Conteneur de notifications toast -->
  <div class="toast-container" id="toastContainer"></div>

  <script>
    const CURRENT_ROLE = <?php echo (int)$currentRole; ?>;
    const CURRENT_LOGIN = <?php echo json_encode($currentLogin, JSON_UNESCAPED_UNICODE); ?>;
    const RESOURCE_ID = 1;
    const RESOURCE_NAME = "Véhicule";
    const APPROVED_RESERVATIONS = <?php echo json_encode($approvedReservations, JSON_UNESCAPED_UNICODE); ?>;

    const agendaGrid      = document.getElementById("agendaGrid");
    const historyList     = document.getElementById("historyList");
    const historyEmpty    = document.getElementById("historyEmpty");
    const historyToggleBtn = document.getElementById("historyToggleBtn");
    const legendList      = document.getElementById("legendList");
    const weekRangeLabel  = document.getElementById("weekRangeLabel");
    const prevWeekBtn     = document.getElementById("prevWeekBtn");
    const nextWeekBtn     = document.getElementById("nextWeekBtn");
    const todayBtn        = document.getElementById("todayBtn");
    const vehicleButtons  = Array.from(document.querySelectorAll(".vehicle-btn"));
    const reservationModal = document.getElementById("reservationModal");
    const selectedSlotInfo = document.getElementById("selectedSlotInfo");
    const teacherNameInput = document.getElementById("teacherName");
    const teacherSuggestionsList = document.getElementById("teacherSuggestionsVehicule");
    const durationSelect   = document.getElementById("durationSelect");
    const cancelReservation  = document.getElementById("cancelReservation");
    const confirmReservation = document.getElementById("confirmReservation");
    const toastContainer   = document.getElementById("toastContainer");
    const dateJumpPopover  = document.getElementById("dateJumpPopover");
    const cancelModal      = document.getElementById("cancelModal");
    const cancelSlotInfo   = document.getElementById("cancelSlotInfo");
    const cancelModalClose = document.getElementById("cancelModalClose");
    const confirmCancelBtn = document.getElementById("confirmCancelBtn");

    const times = ["8h35", "9h35", "10h45", "11h45", "13h15", "14h15", "15h25", "16h25", "17h20"];
    const teacherColors = {};
    const colorPalette = ["#e74c3c","#3498db","#27ae60","#f39c12","#9b59b6","#1abc9c","#e67e22","#2ecc71","#34495e","#d35400","#8e44ad","#16a085"];
    const teacherNames = <?php echo json_encode(array_values($teacherNames), JSON_UNESCAPED_UNICODE); ?>;
    const isProf  = CURRENT_ROLE === 3;
    const isAdmin = [1, 4].includes(CURRENT_ROLE);

    let cancelModalReservationId = null;

    let colorIndex = 0;
    let selectedSlot = null;
    let currentWeekOffset = 0;
    let weekDays = [];
    let weekDayLabelByIso = {};
    let historyLoaded = false;
    let historyExpanded = false;

    function updateTeacherSuggestions(inputValue) {
      if (!teacherSuggestionsList) return;
      const query = inputValue.trim().toLowerCase();
      teacherSuggestionsList.innerHTML = "";
      if (query === "") return;

      const matches = teacherNames
        .filter(name => name.toLowerCase().includes(query))
        .slice(0, 12);

      matches.forEach((name) => {
        const option = document.createElement("option");
        option.value = name;
        teacherSuggestionsList.appendChild(option);
      });
    }

    teacherNameInput.addEventListener("input", () => updateTeacherSuggestions(teacherNameInput.value));
    teacherNameInput.addEventListener("focus", () => updateTeacherSuggestions(teacherNameInput.value));
    teacherNameInput.addEventListener("blur", () => {
      window.setTimeout(() => {
        teacherSuggestionsList.innerHTML = "";
      }, 120);
    });
    let selectedVehicle = "Renault";
    let calYear  = new Date().getFullYear();
    let calMonth = new Date().getMonth();

    // ── Toast ─────────────────────────────────────────
    function showToast(message, type = "info") {
      const toast = document.createElement("div");
      toast.className = `toast toast-${type}`;
      toast.textContent = message;
      toastContainer.appendChild(toast);
      requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add("toast-show"));
      });
      setTimeout(() => {
        toast.classList.remove("toast-show");
        setTimeout(() => toast.remove(), 250);
      }, 3500);
    }

    // ── Helpers ───────────────────────────────────────
    function pad2(v) { return String(v).padStart(2, "0"); }

    function todayISO() {
      const d = new Date();
      return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
    }

    function normalizeVehicle(v) {
      return ["Renault","Toyota","Peugeot"].includes(v) ? v : "Renault";
    }

    function isPastDay(dayKey) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey)) return false;
      const today = new Date(); today.setHours(0,0,0,0);
      return new Date(`${dayKey}T00:00:00`) < today;
    }

    function displayDayLabel(dayValue) {
      if (weekDayLabelByIso[dayValue]) return weekDayLabelByIso[dayValue];
      if (/^\d{4}-\d{2}-\d{2}$/.test(dayValue)) {
        const [, m, d] = dayValue.split("-");
        return `${d}/${m}`;
      }
      return dayValue;
    }

    // ── Semaine ───────────────────────────────────────
    function buildWeekDays(offset = 0) {
      const names = ["Lundi","Mardi","Mercredi","Jeudi","Vendredi"];
      const now   = new Date();
      const monday = new Date(now);
      monday.setHours(0,0,0,0);
      monday.setDate(now.getDate() - (now.getDay()+6)%7 + offset*7);
      return names.map((name, i) => {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const iso     = `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
        const display = `${name} ${pad2(d.getDate())}/${pad2(d.getMonth()+1)}`;
        return { iso, display, date: d };
      });
    }

    function updateWeekRangeLabel() {
      if (!weekDays.length) return;
      const first = weekDays[0].display.split(" ")[1];
      const last  = weekDays[weekDays.length-1].display.split(" ")[1];
      weekRangeLabel.textContent = `Semaine du ${first} au ${last}`;
    }

    // ── Layout calendrier ─────────────────────────────
    function applyCalendarLayout() {
      const headers = Array.from(agendaGrid.querySelectorAll(".day-header"));
      headers.forEach((h, i) => {
        if (i < weekDays.length) h.textContent = weekDays[i].display;
        else h.remove();
      });
      times.forEach(time => {
        const rowSlots = Array.from(agendaGrid.querySelectorAll(`.slot[data-time="${time}"]`));
        rowSlots.forEach((s, i) => {
          if (i < weekDays.length) {
            s.dataset.day      = weekDays[i].iso;
            s.dataset.dayLabel = weekDays[i].display;
          } else {
            s.remove();
          }
        });
      });
    }

    // ── États aujourd'hui / passé ─────────────────────
    function applyDayStates() {
      const today = todayISO();

      agendaGrid.querySelectorAll(".day-header").forEach((h, i) => {
        h.classList.toggle("today", i < weekDays.length && weekDays[i].iso === today);
      });

      agendaGrid.querySelectorAll(".slot").forEach(slot => {
        const past = isPastDay(slot.dataset.day);
        slot.classList.toggle("past", past);
        if (slot.classList.contains("reserved")) {
          slot.disabled = !isAdmin;
        } else {
          slot.disabled = past;
        }
      });

      if (todayBtn) todayBtn.disabled = currentWeekOffset === 0;
    }

    // ── Couleurs professeurs ──────────────────────────
    function getTeacherColor(name) {
      if (!teacherColors[name]) {
        teacherColors[name] = colorPalette[colorIndex % colorPalette.length];
        colorIndex++;
      }
      return teacherColors[name];
    }

    function renderLegend(activeNames = null) {
      const names = Array.isArray(activeNames) ? activeNames : Object.keys(teacherColors);
      if (!names.length) {
        legendList.innerHTML = '<p class="legend-empty">Aucun professeur enregistré.</p>';
        return;
      }
      legendList.innerHTML = "";
      names.forEach(name => {
        const item = document.createElement("div");
        item.className = "legend-item";
        item.innerHTML = `<span class="legend-color" style="background:${teacherColors[name]}"></span><span class="legend-name">${name}</span>`;
        legendList.appendChild(item);
      });
    }

    // ── Réservations agenda ───────────────────────────
    function clearAgendaReservations() {
      agendaGrid.querySelectorAll(".slot").forEach(s => {
        s.classList.remove("reserved");
        s.style.backgroundColor = "";
        s.style.color = "";
        s.innerHTML = "";
        s.title = "";
        s.disabled = false;
        delete s.dataset.reservationId;
      });
    }

    function markReservation(dayKey, startTime, duration, teacherName, reservationId = null) {
      const startIndex = times.indexOf(startTime);
      if (startIndex < 0) return;
      const endIndex    = Math.min(startIndex + duration - 1, times.length - 1);
      const endTime     = times[endIndex];
      const color       = getTeacherColor(teacherName);
      const daySlots    = Array.from(agendaGrid.querySelectorAll(`.slot[data-day="${dayKey}"]`));

      for (let i = startIndex; i <= endIndex; i++) {
        const slot = daySlots[i];
        if (!slot) continue;
        slot.classList.add("reserved");
        slot.style.backgroundColor = color;
        slot.style.color = "#fff";
        slot.disabled = !isAdmin;
        slot.title = `${teacherName} — ${startTime} à ${endTime} (${duration}h)`;
        if (reservationId) slot.dataset.reservationId = reservationId;

        if (i === startIndex) {
          const short = teacherName.length > 13 ? teacherName.substring(0,12)+"…" : teacherName;
          slot.innerHTML = `<span class="slot-name">${short}</span><span class="slot-duration">${startTime}–${endTime}</span>`;
        } else {
          slot.innerHTML = `<span class="slot-continuation">↕</span>`;
        }
      }
    }

    function renderApprovedReservationsForWeek() {
      clearAgendaReservations();
      const activeTeachers = new Set();
      if (!Array.isArray(APPROVED_RESERVATIONS)) { renderLegend([]); return; }

      APPROVED_RESERVATIONS.forEach(r => {
        if (!weekDayLabelByIso[r.day]) return;
        if (normalizeVehicle(r.vehicle_name || "Renault") !== selectedVehicle) return;
        const teacher = r.demandeur || "Utilisateur";
        markReservation(r.day, r.heure_debut || "", Number(r.duree||1), teacher, r.id || null);
        if (!isPastDay(r.day)) activeTeachers.add(teacher);
      });

      renderLegend(Array.from(activeTeachers));
      applyDayStates();
    }

    // ── Historique ────────────────────────────────────
    function appendHistory(dayLabel, startTime, duration, teacherName, statusText, isRequest = false, vehicleName = null) {
      const si = times.indexOf(startTime);
      const endTime = si >= 0 && si+duration-1 < times.length ? times[si+duration-1] : startTime;
      if (historyEmpty) historyEmpty.remove();
      const item = document.createElement("div");
      item.className = "history-item";
      const action  = isRequest ? "Demande de réservation" : "Véhicule réservé";
      const vehicle = vehicleName ? `<span>Véhicule : ${vehicleName}</span>` : "";
      item.innerHTML = `<strong>${dayLabel}</strong><span>${action} de ${startTime} à ${endTime}</span>${vehicle}<span>Professeur : ${teacherName}</span><span>Durée : ${duration} heure(s)</span><span>Statut : ${statusText}</span>`;
      historyList.prepend(item);
      updateHistoryListCompact();
    }

    function updateHistoryListCompact() {
      const count = historyList.querySelectorAll(".history-item").length;
      const shouldCompact = count > 2;
      if (historyToggleBtn) {
        historyToggleBtn.classList.toggle("hidden", !shouldCompact);
        historyToggleBtn.textContent = historyExpanded ? "Voir moins" : "Voir plus";
      }
      historyList.classList.toggle("expanded", historyExpanded && shouldCompact);
      historyList.classList.toggle("compact", !historyExpanded && shouldCompact);
    }

    function loadHistoryOnce() {
      if (historyLoaded || !Array.isArray(APPROVED_RESERVATIONS)) return;
      APPROVED_RESERVATIONS.forEach(r => {
        const label    = displayDayLabel(r.day || "");
        const teacher  = r.demandeur || "Utilisateur";
        appendHistory(label, r.heure_debut||"", Number(r.duree||1), teacher, "Confirmée", false, r.vehicle_name||null);
      });
      updateHistoryListCompact();
      historyLoaded = true;
    }

    // ── Sélection du véhicule ─────────────────────────
    function setActiveVehicle(name) {
      selectedVehicle = normalizeVehicle(name);
      vehicleButtons.forEach(b => b.classList.toggle("active", b.dataset.vehicle === selectedVehicle));
      selectedSlot = null;
      refreshWeek();
    }

    vehicleButtons.forEach(b => b.addEventListener("click", () => setActiveVehicle(b.dataset.vehicle)));

    // ── Semaine : refresh ─────────────────────────────
    function refreshWeek() {
      weekDays         = buildWeekDays(currentWeekOffset);
      weekDayLabelByIso = Object.fromEntries(weekDays.map(d => [d.iso, d.display]));
      updateWeekRangeLabel();
      applyCalendarLayout();
      renderApprovedReservationsForWeek();
    }

    // ── Navigation semaine ────────────────────────────
    function isoToWeekOffset(iso) {
      const target = new Date(`${iso}T00:00:00`);
      const tMon = new Date(target);
      tMon.setDate(target.getDate() - (target.getDay() + 6) % 7);
      tMon.setHours(0, 0, 0, 0);
      const now = new Date();
      const nMon = new Date(now);
      nMon.setDate(now.getDate() - (now.getDay() + 6) % 7);
      nMon.setHours(0, 0, 0, 0);
      return Math.round((tMon - nMon) / (7 * 24 * 60 * 60 * 1000));
    }

    prevWeekBtn.addEventListener("click", () => { currentWeekOffset--; selectedSlot = null; refreshWeek(); });
    nextWeekBtn.addEventListener("click", () => { currentWeekOffset++; selectedSlot = null; refreshWeek(); });
    todayBtn.addEventListener("click", () => {
      if (currentWeekOffset === 0) return;
      currentWeekOffset = 0; selectedSlot = null; refreshWeek();
    });

    function buildCalendar() {
      const today = todayISO();
      const wMon  = weekDays.length ? weekDays[0].iso : null;
      const wFri  = weekDays.length ? weekDays[4].iso : null;
      const first = new Date(calYear, calMonth, 1);
      const days  = new Date(calYear, calMonth + 1, 0).getDate();
      const start = (first.getDay() + 6) % 7;
      const label = new Intl.DateTimeFormat("fr-FR", { month:"long", year:"numeric" }).format(first);
      let h = `<div class="cal-header">
        <button class="cal-nav" id="calPrev">‹</button>
        <span class="cal-month-label">${label}</span>
        <button class="cal-nav" id="calNext">›</button>
      </div><div class="cal-grid">
        <span class="cal-dow">L</span><span class="cal-dow">M</span><span class="cal-dow">M</span>
        <span class="cal-dow">J</span><span class="cal-dow">V</span><span class="cal-dow">S</span>
        <span class="cal-dow">D</span>`;
      for (let i = 0; i < start; i++) h += `<span class="cal-empty"></span>`;
      for (let d = 1; d <= days; d++) {
        const iso = `${calYear}-${pad2(calMonth+1)}-${pad2(d)}`;
        const dow = new Date(`${iso}T00:00:00`).getDay();
        let cls = "cal-day";
        if (iso === today) cls += " cal-today";
        if (wMon && iso >= wMon && iso <= wFri) cls += " cal-cur-week";
        if (dow === 0 || dow === 6) cls += " cal-weekend";
        h += `<button class="${cls}" data-iso="${iso}">${d}</button>`;
      }
      h += `</div>`;
      dateJumpPopover.innerHTML = h;
      dateJumpPopover.querySelector("#calPrev").addEventListener("click", e => {
        e.stopPropagation(); if (--calMonth < 0) { calMonth=11; calYear--; } buildCalendar();
      });
      dateJumpPopover.querySelector("#calNext").addEventListener("click", e => {
        e.stopPropagation(); if (++calMonth > 11) { calMonth=0; calYear++; } buildCalendar();
      });
      dateJumpPopover.querySelectorAll(".cal-day").forEach(btn => {
        btn.addEventListener("click", e => {
          e.stopPropagation();
          currentWeekOffset = isoToWeekOffset(btn.dataset.iso);
          selectedSlot = null; refreshWeek();
          dateJumpPopover.classList.remove("open");
        });
      });
    }

    weekRangeLabel.addEventListener("click", e => {
      e.stopPropagation();
      if (weekDays.length) { const d = new Date(weekDays[0].iso+"T00:00:00"); calYear=d.getFullYear(); calMonth=d.getMonth(); }
      buildCalendar();
      const rect = weekRangeLabel.getBoundingClientRect();
      dateJumpPopover.style.top  = (rect.bottom + 6) + "px";
      dateJumpPopover.style.left = rect.left + "px";
      dateJumpPopover.classList.toggle("open");
    });
    document.addEventListener("click", e => {
      if (!dateJumpPopover.contains(e.target) && e.target !== weekRangeLabel)
        dateJumpPopover.classList.remove("open");
    });

    // ── Clic sur un créneau ───────────────────────────
    if (isProf && confirmReservation) confirmReservation.textContent = "Demander";

    agendaGrid.addEventListener("click", e => {
      const slot = e.target.closest(".slot");
      if (!slot) return;

      if (slot.classList.contains("reserved")) {
        if (isAdmin && slot.dataset.reservationId) {
          cancelSlotInfo.textContent = slot.title || "Cette réservation";
          cancelModalReservationId = parseInt(slot.dataset.reservationId, 10);
          cancelModal.classList.add("active");
        } else {
          showToast(`Déjà réservé : ${slot.title || "créneau pris"}`, "error");
        }
        return;
      }
      if (slot.classList.contains("past") || slot.disabled) return;

      agendaGrid.querySelectorAll(".slot.selected").forEach(s => s.classList.remove("selected"));
      slot.classList.add("selected");

      selectedSlot = slot;
      teacherNameInput.value = "";
      durationSelect.value   = "1";
      updateSelectedSlotInfoText();
      reservationModal.classList.add("active");
    });

    function updateSelectedSlotInfoText() {
      if (!selectedSlot || !selectedSlotInfo) return;
      const dayLabel = selectedSlot.dataset.dayLabel || selectedSlot.dataset.day;
      const dur = parseInt(durationSelect.value, 10);
      if (dur === times.length) {
        selectedSlotInfo.textContent = `${dayLabel} — journée entière (${selectedVehicle})`;
      } else {
        selectedSlotInfo.textContent = `${dayLabel} à ${selectedSlot.dataset.time} (${selectedVehicle})`;
      }
    }

    durationSelect.addEventListener("change", updateSelectedSlotInfoText);

    // ── Modale : annuler ──────────────────────────────
    function closeModal() {
      reservationModal.classList.remove("active");
      if (selectedSlot) { selectedSlot.classList.remove("selected"); selectedSlot = null; }
    }

    cancelReservation.addEventListener("click", closeModal);
    reservationModal.addEventListener("click", e => { if (e.target === reservationModal) closeModal(); });

    // ── Modale : confirmer ────────────────────────────
    confirmReservation.addEventListener("click", async () => {
      if (!selectedSlot) return;

      const teacherName   = teacherNameInput.value.trim();
      const duration      = parseInt(durationSelect.value, 10);
      const dayKey        = selectedSlot.dataset.day;
      const dayLabel      = selectedSlot.dataset.dayLabel || dayKey;
      const startTime     = selectedSlot.dataset.time;
      const vehicleName   = selectedVehicle;
      const isFullDay     = duration === 9;
      const effStart      = isFullDay ? times[0] : startTime;
      const effDuration   = isFullDay ? times.length : duration;
      const startIndex    = times.indexOf(effStart);
      const endIndex      = startIndex + effDuration - 1;

      if (startIndex < 0 || endIndex >= times.length) {
        showToast("La réservation dépasse la fin de la journée.", "error");
        return;
      }
      if (!teacherName) {
        showToast("Le nom du professeur est obligatoire.", "error");
        return;
      }
      if (!vehicleName) {
        showToast("Veuillez choisir un véhicule.", "error");
        return;
      }

      try {
        const response = await fetch("reservation_request.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ resource_id: RESOURCE_ID, day: dayKey, heure_debut: effStart, duree: effDuration, vehicle_name: vehicleName, teacher_name: teacherName, full_day: isFullDay })
        });
        const payload = await response.json();

        if (!response.ok || !payload.ok) {
          const msgs = { slot_taken: "Ce créneau est déjà réservé.", invalid_duration: "Durée invalide pour ce créneau.", invalid_vehicle: "Véhicule invalide.", invalid_teacher: "Professeur invalide." };
          showToast(msgs[payload.error] || (isProf ? "Demande impossible. Merci de réessayer." : "Réservation impossible."), "error");
          return;
        }

        if (isProf) {
          appendHistory(dayLabel, effStart, effDuration, teacherName, "En attente de confirmation", true, vehicleName);
          showToast("Demande envoyée — en attente de confirmation.", "success");
        } else {
          APPROVED_RESERVATIONS.push({ day: dayKey, heure_debut: effStart, duree: effDuration, demandeur: teacherName, vehicle_name: vehicleName });
          renderApprovedReservationsForWeek();
          appendHistory(dayLabel, effStart, effDuration, teacherName, "Confirmée", false, vehicleName);
          showToast("Réservation enregistrée.", "success");
        }

        closeModal();
      } catch (err) {
        showToast("Erreur réseau. Merci de réessayer.", "error");
      }
    });

    // ── Modale annulation ─────────────────────────────
    cancelModalClose.addEventListener("click", () => cancelModal.classList.remove("active"));
    cancelModal.addEventListener("click", e => { if (e.target === cancelModal) cancelModal.classList.remove("active"); });

    confirmCancelBtn.addEventListener("click", async () => {
      if (!cancelModalReservationId) return;
      try {
        const res = await fetch("reservation_request.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "cancel", reservation_id: cancelModalReservationId })
        });
        const payload = await res.json();
        cancelModal.classList.remove("active");
        if (!res.ok || !payload.ok) {
          showToast("Impossible d'annuler cette réservation.", "error");
          return;
        }
        const idx = APPROVED_RESERVATIONS.findIndex(r => r.id === cancelModalReservationId);
        if (idx !== -1) APPROVED_RESERVATIONS.splice(idx, 1);
        cancelModalReservationId = null;
        renderApprovedReservationsForWeek();
        showToast("Réservation annulée.", "success");
      } catch {
        showToast("Erreur réseau. Merci de réessayer.", "error");
      }
    });

    // ── Init ──────────────────────────────────────────
    setActiveVehicle("Renault");
    loadHistoryOnce();

    historyToggleBtn.addEventListener("click", () => {
      historyExpanded = !historyExpanded;
      updateHistoryListCompact();
    });
  </script>

</body>
</html>
