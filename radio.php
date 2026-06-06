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
        <li><a href="vehicule.php">V&eacute;hicule</a></li>
        <li><a href="radio.php" class="active">Salle radio</a></li>
        <li><a href="mobile.php">Classe mobile</a></li>
        <li><a href="reservation_validation.php">Confirmation</a></li>
        <li><a href="register.php">Cr&eacute;er un compte</a></li>
        <li><a href="logout.php">D&eacute;connexion</a></li>
      <?php else: ?>
        <li><a href="vehicule.php">V&eacute;hicule</a></li>
        <li><a href="radio.php" class="active">Salle radio</a></li>
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
      <h2 class="agenda-title">Planning salle radio</h2>

      <div class="week-nav">
        <button type="button" class="week-nav-btn" id="prevWeekBtn" aria-label="Semaine précédente">&larr;</button>
        <button type="button" class="week-nav-btn today-btn" id="todayBtn" disabled>Aujourd'hui</button>
        <span class="week-range" id="weekRangeLabel" title="Cliquer pour choisir une date"></span>
        <button type="button" class="week-nav-btn" id="nextWeekBtn" aria-label="Semaine suivante">&rarr;</button>
      </div>
      <div class="date-jump-popover" id="dateJumpPopover"></div>

      <div class="agenda-grid" id="agenda"></div>
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

<div class="modal-overlay" id="modal">
  <div class="modal-box">
    <h2>Nouvelle réservation</h2>
    <p class="modal-info" id="slotInfo">Créneau sélectionné :</p>

    <div class="modal-group">
      <label for="teacher">Nom du professeur</label>
      <input type="text" id="teacher" list="teacherSuggestionsRadio" placeholder="Ex : Mme Dupont" autocomplete="off">
      <datalist id="teacherSuggestionsRadio"></datalist>
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
      <button id="cancelBtn" type="button" class="modal-btn cancel-btn">Annuler</button>
      <button id="confirmBtn" type="button" class="modal-btn confirm-btn">Valider</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const CURRENT_ROLE = <?php echo (int)$currentRole; ?>;
const CURRENT_LOGIN = <?php echo json_encode($currentLogin, JSON_UNESCAPED_UNICODE); ?>;
const RESOURCE_ID   = 2;
const RESOURCE_NAME = "Salle radio";
const APPROVED_RESERVATIONS = <?php echo json_encode($approvedReservations, JSON_UNESCAPED_UNICODE); ?>;

const times = ["8h35","9h35","10h45","11h45","13h15","14h15","15h25","16h25","17h20"];
const agenda          = document.getElementById("agenda");
const historyList     = document.getElementById("historyList");
const historyEmpty    = document.getElementById("historyEmpty");
const historyToggleBtn = document.getElementById("historyToggleBtn");
const legendList      = document.getElementById("legendList");
const weekRangeLabel  = document.getElementById("weekRangeLabel");
const prevWeekBtn     = document.getElementById("prevWeekBtn");
const nextWeekBtn     = document.getElementById("nextWeekBtn");
const todayBtn        = document.getElementById("todayBtn");
const modal           = document.getElementById("modal");
const teacherInput    = document.getElementById("teacher");
const teacherSuggestionsList = document.getElementById("teacherSuggestionsRadio");
const durationSelect  = document.getElementById("duration");
const slotInfo        = document.getElementById("slotInfo");
const confirmBtn      = document.getElementById("confirmBtn");
const toastContainer  = document.getElementById("toastContainer");
const dateJumpPopover = document.getElementById("dateJumpPopover");
const cancelModal     = document.getElementById("cancelModal");
const cancelSlotInfo  = document.getElementById("cancelSlotInfo");
const cancelModalClose = document.getElementById("cancelModalClose");
const confirmCancelBtn = document.getElementById("confirmCancelBtn");
const isProf  = CURRENT_ROLE === 3;
const isAdmin = [1, 4].includes(CURRENT_ROLE);
const teacherNames = <?php echo json_encode(array_values($teacherNames), JSON_UNESCAPED_UNICODE); ?>;

const colors = ["#ff6b6b","#4dabf7","#51cf66","#fcc419","#845ef7","#ff922b","#e74c3c","#1abc9c","#9b59b6","#f39c12"];
const teacherColors = {};

let cancelModalReservationId = null;
let selected = null;
let currentWeekOffset = 0;
let calYear  = new Date().getFullYear();
let calMonth = new Date().getMonth();
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

teacherInput.addEventListener("input", () => updateTeacherSuggestions(teacherInput.value));
teacherInput.addEventListener("focus", () => updateTeacherSuggestions(teacherInput.value));
teacherInput.addEventListener("blur", () => {
  window.setTimeout(() => {
    teacherSuggestionsList.innerHTML = "";
  }, 120);
});

// ── Toast ─────────────────────────────────────────
function showToast(message, type = "info") {
  const t = document.createElement("div");
  t.className = `toast toast-${type}`;
  t.textContent = message;
  toastContainer.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add("toast-show")));
  setTimeout(() => { t.classList.remove("toast-show"); setTimeout(() => t.remove(), 250); }, 3500);
}

// ── Helpers ───────────────────────────────────────
function pad2(v) { return String(v).padStart(2,"0"); }

function todayISO() {
  const d = new Date();
  return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
}

function isPastDay(dayKey) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey)) return false;
  const t = new Date(); t.setHours(0,0,0,0);
  return new Date(`${dayKey}T00:00:00`) < t;
}

function displayDayLabel(v) {
  if (weekDayLabelByIso[v]) return weekDayLabelByIso[v];
  if (/^\d{4}-\d{2}-\d{2}$/.test(v)) { const [,m,d]=v.split("-"); return `${d}/${m}`; }
  return v;
}

// ── Semaine ───────────────────────────────────────
function buildWeekDays(offset=0) {
  const names = ["Lundi","Mardi","Mercredi","Jeudi","Vendredi"];
  const now = new Date();
  const monday = new Date(now);
  monday.setHours(0,0,0,0);
  monday.setDate(now.getDate() - (now.getDay()+6)%7 + offset*7);
  return names.map((name,i) => {
    const d = new Date(monday); d.setDate(monday.getDate()+i);
    const iso = `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
    return { iso, display:`${name} ${pad2(d.getDate())}/${pad2(d.getMonth()+1)}`, date:d };
  });
}

function updateWeekRangeLabel() {
  if (!weekDays.length) return;
  weekRangeLabel.textContent = `Semaine du ${weekDays[0].display.split(" ")[1]} au ${weekDays[4].display.split(" ")[1]}`;
}

// ── Grille agenda (générée dynamiquement) ─────────
function getColor(name) {
  if (!teacherColors[name]) teacherColors[name] = colors[Object.keys(teacherColors).length % colors.length];
  return teacherColors[name];
}

function renderAgendaGrid() {
  if (!agenda) return;
  agenda.innerHTML = "";

  const today = todayISO();

  const corner = document.createElement("div");
  corner.className = "corner";
  agenda.appendChild(corner);

  weekDays.forEach(day => {
    const h = document.createElement("div");
    h.className = "day-header" + (day.iso === today ? " today" : "");
    h.textContent = day.display;
    agenda.appendChild(h);
  });

  times.forEach(time => {
    const label = document.createElement("div");
    label.className = "time-label";
    label.textContent = time;
    agenda.appendChild(label);

    weekDays.forEach(day => {
      const slot = document.createElement("button");
      slot.type = "button";
      slot.className = "slot" + (isPastDay(day.iso) ? " past" : "");
      slot.dataset.day      = day.iso;
      slot.dataset.dayLabel = day.display;
      slot.dataset.time     = time;
      slot.disabled         = isPastDay(day.iso);
      agenda.appendChild(slot);
    });
  });
}

// ── Réservations ──────────────────────────────────
function markReservation(dayKey, startTime, duration, name, reservationId = null) {
  const si = times.indexOf(startTime);
  if (si < 0) return;
  const ei    = Math.min(si+duration-1, times.length-1);
  const color = getColor(name);
  const slots = Array.from(agenda.querySelectorAll(`.slot[data-day="${dayKey}"]`));
  const short = name.length > 13 ? name.substring(0,12)+"…" : name;
  const endTime = times[ei];

  for (let i = si; i <= ei; i++) {
    const s = slots[i]; if (!s) continue;
    s.classList.add("reserved");
    s.style.background = color;
    s.style.color = "#fff";
    s.disabled = !isAdmin;
    s.title = `${name} — ${startTime} à ${endTime} (${duration}h)`;
    if (reservationId) s.dataset.reservationId = reservationId;
    s.innerHTML = i === si
      ? `<span class="slot-name">${short}</span><span class="slot-duration">${startTime}–${endTime}</span>`
      : `<span class="slot-continuation">↕</span>`;
  }
}

function updateLegend(activeNames = null) {
  const names = Array.isArray(activeNames) ? activeNames : Object.keys(teacherColors);
  const entries = names.filter(n => teacherColors[n]);
  if (!entries.length) { legendList.innerHTML='<p class="legend-empty">Aucun professeur.</p>'; return; }
  legendList.innerHTML = "";
  entries.forEach(name => {
    const item = document.createElement("div"); item.className = "legend-item";
    item.innerHTML = `<div class="legend-color" style="background:${teacherColors[name]}"></div><span class="legend-name">${name}</span>`;
    legendList.appendChild(item);
  });
}

function renderApprovedReservationsForWeek() {
  if (!Array.isArray(APPROVED_RESERVATIONS)) { updateLegend([]); return; }
  const active = new Set();
  APPROVED_RESERVATIONS.forEach(r => {
    if (!weekDayLabelByIso[r.day]) return;
    const name = r.demandeur || "Utilisateur";
    markReservation(r.day, r.heure_debut||"", Number(r.duree||1), name, r.id || null);
    if (!isPastDay(r.day)) active.add(name);
  });
  updateLegend(Array.from(active));
}

// ── Historique ────────────────────────────────────
function appendHistory(dayLabel, startTime, duration, name, statusText, isRequest=false) {
  historyEmpty?.remove();
  const si = times.indexOf(startTime);
  const endTime = si >= 0 && si+duration-1 < times.length ? times[si+duration-1] : startTime;
  const item = document.createElement("div"); item.className = "history-item";
  const action = isRequest ? "Demande" : "Réservation";
  item.innerHTML = `<strong>${dayLabel}</strong><span>${action} ${RESOURCE_NAME} — ${startTime} à ${endTime}</span><span>Professeur : ${name}</span><span>Durée : ${duration}h — ${statusText}</span>`;
  historyList.prepend(item);
  updateHistoryListCompact();
}

function updateHistoryListCompact() {
  const count = historyList.querySelectorAll(".history-item").length;
  const compact = count > 2;
  if (historyToggleBtn) { historyToggleBtn.classList.toggle("hidden", !compact); historyToggleBtn.textContent = historyExpanded ? "Voir moins" : "Voir plus"; }
  historyList.classList.toggle("expanded", historyExpanded && compact);
  historyList.classList.toggle("compact", !historyExpanded && compact);
}

function loadHistoryOnce() {
  if (historyLoaded || !Array.isArray(APPROVED_RESERVATIONS)) return;
  APPROVED_RESERVATIONS.forEach(r => {
    appendHistory(displayDayLabel(r.day||""), r.heure_debut||"", Number(r.duree||1), r.demandeur||"Utilisateur", "Confirmée");
  });
  updateHistoryListCompact();
  historyLoaded = true;
}

// ── Refresh ───────────────────────────────────────
function refreshWeek() {
  weekDays         = buildWeekDays(currentWeekOffset);
  weekDayLabelByIso = Object.fromEntries(weekDays.map(d=>[d.iso,d.display]));
  updateWeekRangeLabel();
  renderAgendaGrid();
  renderApprovedReservationsForWeek();
  if (todayBtn) todayBtn.disabled = currentWeekOffset === 0;
}

// ── Navigation ────────────────────────────────────
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

prevWeekBtn.addEventListener("click", () => { currentWeekOffset--; selected=null; refreshWeek(); });
nextWeekBtn.addEventListener("click", () => { currentWeekOffset++; selected=null; refreshWeek(); });
todayBtn.addEventListener("click", () => { if (currentWeekOffset===0) return; currentWeekOffset=0; selected=null; refreshWeek(); });

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
      selected = null; refreshWeek();
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

// ── Clic créneau ──────────────────────────────────
if (isProf && confirmBtn) confirmBtn.textContent = "Demander";

function updateSlotInfoText() {
  if (!selected || !slotInfo) return;
  const dayLabel = selected.dataset.dayLabel || selected.dataset.day;
  const dur = parseInt(durationSelect.value, 10);
  slotInfo.textContent = dur === times.length
    ? `${dayLabel} — journée entière`
    : `${dayLabel} à ${selected.dataset.time}`;
}

agenda.addEventListener("click", e => {
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

  agenda.querySelectorAll(".slot.selected").forEach(s => s.classList.remove("selected"));
  slot.classList.add("selected");
  selected = slot;
  teacherInput.value = ""; durationSelect.value = "1";
  updateSlotInfoText();
  modal.classList.add("active");
});

durationSelect.addEventListener("change", updateSlotInfoText);

// ── Modale ────────────────────────────────────────
function closeModal() {
  modal.classList.remove("active");
  if (selected) { selected.classList.remove("selected"); selected = null; }
}

document.getElementById("cancelBtn").addEventListener("click", closeModal);
modal.addEventListener("click", e => { if (e.target === modal) closeModal(); });

confirmBtn.addEventListener("click", async () => {
  if (!selected) return;
  const name     = teacherInput.value.trim();
  const duration = parseInt(durationSelect.value, 10);
  const dayKey   = selected.dataset.day;
  const dayLabel = selected.dataset.dayLabel || dayKey;
  const startTime = selected.dataset.time;
  const isFullDay = duration === 9;
  const effStart  = isFullDay ? times[0] : startTime;
  const effDur    = isFullDay ? times.length : duration;

  if (!name) { showToast("Le nom du professeur est obligatoire.", "error"); return; }

  try {
    const response = await fetch("reservation_request.php", {
      method:"POST", headers:{"Content-Type":"application/json"},
      body: JSON.stringify({ resource_id:RESOURCE_ID, day:dayKey, heure_debut:effStart, duree:effDur, teacher_name:name, full_day:isFullDay })
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) {
      const msgs = { slot_taken:"Ce créneau est déjà réservé.", invalid_duration:"Durée invalide pour ce créneau.", invalid_teacher:"Professeur invalide." };
      showToast(msgs[payload.error] || (isProf?"Demande impossible.":"Réservation impossible."), "error"); return;
    }
    if (isProf) {
      appendHistory(dayLabel, effStart, effDur, name, "En attente de confirmation", true);
      showToast("Demande envoyée — en attente de confirmation.", "success");
    } else {
      APPROVED_RESERVATIONS.push({ id: payload.id || null, day:dayKey, heure_debut:effStart, duree:effDur, demandeur:name });
      refreshWeek();
      appendHistory(dayLabel, effStart, effDur, name, "Confirmée");
      showToast("Réservation enregistrée.", "success");
    }
    closeModal();
  } catch(err) {
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
    refreshWeek();
    showToast("Réservation annulée.", "success");
  } catch {
    showToast("Erreur réseau. Merci de réessayer.", "error");
  }
});

// ── Init ──────────────────────────────────────────
refreshWeek();
loadHistoryOnce();

historyToggleBtn.addEventListener("click", () => {
  historyExpanded = !historyExpanded;
  updateHistoryListCompact();
});
</script>

</body>
</html>
