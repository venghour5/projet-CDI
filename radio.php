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
$approvedReservations = fetchApprovedReservations($pdo, 2);
$teacherNames = fetchTeacherNames($pdo);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Réservation Salle Radio</title>
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
      <li><a href="radio.php" class="active">Salle radio</a></li>
      <li><a href="mobile.php">Classe mobile</a></li>
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
      <h2 class="agenda-title">Planning salle radio</h2>
      <div class="week-nav">
        <button type="button" class="week-nav-btn" id="prevWeekBtn" aria-label="Semaine précédente" title="Semaine précédente">&larr;</button>
        <span class="week-range" id="weekRangeLabel"></span>
        <button type="button" class="week-nav-btn" id="nextWeekBtn" aria-label="Semaine suivante" title="Semaine suivante">&rarr;</button>
      </div>

      <div class="agenda-grid" id="agenda">
        <div class="corner"></div>
        <div class="day-header">Lundi</div>
        <div class="day-header">Mardi</div>
        <div class="day-header">Mercredi</div>
        <div class="day-header">Jeudi</div>
        <div class="day-header">Vendredi</div>
        <div class="day-header">Samedi</div>
        <div class="day-header">Dimanche</div>
      </div>
    </section>

    <aside class="history-card">
      <h2 class="history-title">Historique</h2>
      <div class="history-list" id="historyList">
        <p class="history-empty" id="historyEmpty">Aucune réservation.</p>
      </div>
      <button type="button" class="history-toggle-btn hidden" id="historyToggleBtn">Voir plus</button>

      <div class="legend-box">
        <h3 class="legend-title">Professeurs</h3>
        <div class="legend-list" id="legendList">
          <p class="legend-empty">Aucun professeur.</p>
        </div>
      </div>
    </aside>

  </div>
</main>

<div class="modal-overlay" id="modal">
  <div class="modal-box">
    <h2>Nouvelle réservation</h2>
    <p class="modal-info" id="slotInfo">Créneau sélectionné :</p>

    <div class="modal-group">
      <label for="teacher">Nom du professeur</label>
      <input type="text" id="teacher" list="teacherSuggestionsRadio" placeholder="Ex : Mme Dupont" autocomplete="off">
      <datalist id="teacherSuggestionsRadio">
        <?php foreach ($teacherNames as $teacherName): ?>
          <option value="<?php echo htmlspecialchars((string)$teacherName); ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="modal-group">
      <label for="duration">Durée de réservation</label>
      <select id="duration">
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
      <button id="cancel" type="button" class="modal-btn cancel-btn">Annuler</button>
      <button id="confirm" type="button" class="modal-btn confirm-btn">Valider</button>
    </div>
  </div>
</div>

<script>
const CURRENT_ROLE = <?php echo (int)$currentRole; ?>;
const CURRENT_LOGIN = <?php echo json_encode($currentLogin, JSON_UNESCAPED_UNICODE); ?>;
const RESOURCE_ID = 2;
const RESOURCE_NAME = "Salle radio";
const APPROVED_RESERVATIONS = <?php echo json_encode($approvedReservations, JSON_UNESCAPED_UNICODE); ?>;

const times = ["8h35", "9h35", "10h45", "11h45", "13h15", "14h15", "15h25", "16h25", "17h20"];
const agenda = document.getElementById("agenda");
const historyList = document.getElementById("historyList");
const historyEmpty = document.getElementById("historyEmpty");
const historyToggleBtn = document.getElementById("historyToggleBtn");
const legendList = document.getElementById("legendList");
const weekRangeLabel = document.getElementById("weekRangeLabel");
const prevWeekBtn = document.getElementById("prevWeekBtn");
const nextWeekBtn = document.getElementById("nextWeekBtn");

const modal = document.getElementById("modal");
const teacherInput = document.getElementById("teacher");
const durationSelect = document.getElementById("duration");
const slotInfo = document.getElementById("slotInfo");
const confirmButton = document.getElementById("confirm");
const isProf = CURRENT_ROLE === 3;

const colors = ["#ff6b6b", "#4dabf7", "#51cf66", "#fcc419", "#845ef7", "#ff922b"];
const teacherColors = {};

let selected = null;
let currentWeekOffset = 0;
let weekDays = [];
let weekDayLabelByIso = {};
let historyLoaded = false;
let historyExpanded = false;

function updateSlotInfoText() {
  if (!selected || !slotInfo || !durationSelect) return;
  const dayLabel = selected.dataset.dayLabel || selected.dataset.day;
  const durationValue = parseInt(durationSelect.value, 10);
  if (durationValue === times.length) {
    slotInfo.textContent = `Créneau sélectionné : ${dayLabel} de ${times[0]} à ${times[times.length - 1]}`;
  } else {
    slotInfo.textContent = `Créneau sélectionné : ${dayLabel} à ${selected.dataset.time}`;
  }
}

if (isProf && confirmButton) {
  confirmButton.textContent = "Demander";
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

function updateWeekRangeLabel() {
  if (!weekRangeLabel || weekDays.length === 0) return;
  const first = weekDays[0].display.split(" ")[1];
  const last = weekDays[weekDays.length - 1].display.split(" ")[1];
  weekRangeLabel.textContent = `Semaine du ${first} au ${last}`;
}

function displayDayLabel(dayValue) {
  if (weekDayLabelByIso[dayValue]) return weekDayLabelByIso[dayValue];
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

function renderAgendaGrid() {
  if (!agenda) return;
  agenda.style.gridTemplateColumns = "90px repeat(5, 1fr)";
  agenda.innerHTML = "";

  const corner = document.createElement("div");
  corner.className = "corner";
  agenda.appendChild(corner);

  weekDays.forEach((day) => {
    const header = document.createElement("div");
    header.className = "day-header";
    header.textContent = day.display;
    agenda.appendChild(header);
  });

  times.forEach((time) => {
    const label = document.createElement("div");
    label.className = "time-label";
    label.textContent = time;
    agenda.appendChild(label);

    weekDays.forEach((day) => {
      const slot = document.createElement("button");
      slot.type = "button";
      slot.className = "slot";
      slot.dataset.day = day.iso;
      slot.dataset.dayLabel = day.display;
      slot.dataset.time = time;
      agenda.appendChild(slot);
    });
  });
}

function getColor(name) {
  if (!teacherColors[name]) {
    teacherColors[name] = colors[Object.keys(teacherColors).length % colors.length];
  }
  return teacherColors[name];
}

function updateLegend(activeNames = null) {
  const selectedNames = Array.isArray(activeNames) ? activeNames : Object.keys(teacherColors);
  const entries = selectedNames.map((name) => [name, teacherColors[name]]).filter(([, color]) => Boolean(color));
  if (entries.length === 0) {
    legendList.innerHTML = '<p class="legend-empty">Aucun professeur.</p>';
    return;
  }

  legendList.innerHTML = "";
  entries.forEach(([name, color]) => {
    const item = document.createElement("div");
    item.className = "legend-item";
    item.innerHTML = `<div class="legend-color" style="background:${color}"></div><span>${name}</span>`;
    legendList.appendChild(item);
  });
}

function markReservation(dayKey, startTime, duration, name) {
  const startIndex = times.indexOf(startTime);
  if (startIndex < 0) return;

  const color = getColor(name);
  const daySlots = [...document.querySelectorAll(`.slot[data-day="${dayKey}"]`)];

  for (let i = 0; i < duration; i++) {
    const slot = daySlots[startIndex + i];
    if (!slot) continue;
    slot.classList.add("reserved");
    slot.style.background = color;
    slot.style.color = "#fff";
    if (i === 0) slot.textContent = name;
  }
}

function appendHistory(dayLabel, startTime, duration, name, statusText, isRequest = false) {
  historyEmpty?.remove();
  const item = document.createElement("div");
  item.className = "history-item";
  const actionText = isRequest ? "Demande" : "Réservation";
  item.innerHTML = `<strong>${dayLabel}</strong> ${actionText} ${RESOURCE_NAME} - ${name} (${duration}h) - ${statusText}`;
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
  if (!Array.isArray(APPROVED_RESERVATIONS)) { updateLegend([]); return; }
  const activeTeacherNames = new Set();
  APPROVED_RESERVATIONS.forEach((reservation) => {
    const dayKey = reservation.day || "";
    if (!weekDayLabelByIso[dayKey]) return;
    const name = reservation.demandeur || "Utilisateur";
    const duration = Number(reservation.duree || 1);
    markReservation(dayKey, reservation.heure_debut || "", duration, name);
    if (!isPastDay(dayKey)) {
      activeTeacherNames.add(name);
    }
  });
  updateLegend(Array.from(activeTeacherNames));
}

function loadHistoryOnce() {
  if (historyLoaded || !Array.isArray(APPROVED_RESERVATIONS)) return;
  APPROVED_RESERVATIONS.forEach((reservation) => {
    const dayKey = reservation.day || "";
    const dayLabel = displayDayLabel(dayKey);
    const name = reservation.demandeur || "Utilisateur";
    const duration = Number(reservation.duree || 1);
    appendHistory(dayLabel, reservation.heure_debut || "", duration, name, "Confirmée");
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
  renderAgendaGrid();
  renderApprovedReservationsForWeek();
}

refreshWeek();
loadHistoryOnce();

if (prevWeekBtn) {
  prevWeekBtn.addEventListener("click", () => {
    currentWeekOffset -= 1;
    selected = null;
    refreshWeek();
  });
}

if (nextWeekBtn) {
  nextWeekBtn.addEventListener("click", () => {
    currentWeekOffset += 1;
    selected = null;
    refreshWeek();
  });
}

document.addEventListener("click", (e) => {
  if (!e.target.classList.contains("slot")) return;

  if (e.target.classList.contains("reserved")) {
    alert("Déjà réservé");
    return;
  }

  selected = e.target;
  teacherInput.value = "";
  durationSelect.value = "1";
  updateSlotInfoText();
  modal.classList.add("active");
});

if (durationSelect) {
  durationSelect.addEventListener("change", updateSlotInfoText);
}

document.getElementById("cancel").onclick = () => {
  modal.classList.remove("active");
  selected = null;
};

document.getElementById("confirm").onclick = async () => {
  if (!selected) return;

  const name = (teacherInput.value || "").trim();
  const duration = parseInt(durationSelect.value, 10);
  const dayKey = selected.dataset.day;
  const dayLabel = selected.dataset.dayLabel || dayKey;
  const startTime = selected.dataset.time;
  const isFullDay = duration === 9;
  const effectiveStartTime = isFullDay ? times[0] : startTime;
  const effectiveDuration = isFullDay ? times.length : duration;

  if (!name) {
    alert("Nom requis");
    return;
  }

  try {
    const response = await fetch("reservation_request.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ resource_id: RESOURCE_ID, day: dayKey, heure_debut: effectiveStartTime, duree: effectiveDuration, teacher_name: name, full_day: isFullDay })
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) {
      alert(
        payload.error === "slot_taken"
          ? "Déjà réservé"
          : (payload.error === "invalid_duration"
            ? "Durée invalide pour ce créneau."
            : (payload.error === "invalid_teacher"
              ? "Professeur invalide."
            : (CURRENT_ROLE === 3 ? "Demande impossible." : "Réservation impossible."))
          )
      );
      return;
    }

    if (CURRENT_ROLE === 3) {
      appendHistory(dayLabel, effectiveStartTime, effectiveDuration, name, "En attente de confirmation", true);
      alert("Demande envoyée. En attente de confirmation.");
    } else {
      APPROVED_RESERVATIONS.push({ day: dayKey, heure_debut: effectiveStartTime, duree: effectiveDuration, demandeur: name });
      refreshWeek();
      appendHistory(dayLabel, effectiveStartTime, effectiveDuration, name, "Confirmée");
    }

    modal.classList.remove("active");
    teacherInput.value = "";
    selected = null;
  } catch (error) {
    alert("Erreur réseau.");
  }
};
</script>

</body>
</html>









