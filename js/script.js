// ---------- helpers ----------
const $ = (selector, root = document) => root.querySelector(selector);

// Escape text before putting it into HTML (prevents script injection)
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

async function api(url, options = {}) {
  try {
    const res = await fetch(url, options);
    return await res.json();
  } catch (err) {
    return { ok: false, message: 'Could not reach the server. Is it running?' };
  }
}

function showMessage(text, ok) {
  const el = $('#msg');
  if (!el) return;
  el.textContent = text;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
}

// ---------- navigation ----------
function renderNav(user) {
  const page = document.body.dataset.page;
  const link = (href, label, key) =>
    `<a href="${href}"${page === key ? ' aria-current="page"' : ''}>${label}</a>`;

  let html = `<a class="brand" href="index.html">Student Resource Hub</a>`;
  html += link('resources.html', 'Resources', 'resources');
  if (user) {
    html += link('upload.html', 'Upload', 'upload');
    html += link('dashboard.html', 'Dashboard', 'dashboard');
    html += `<button type="button" id="logoutBtn">Log out</button>`;
  } else {
    html += link('login.html', 'Log in', 'login');
    html += link('register.html', 'Register', 'register');
  }
  $('#nav').innerHTML = html;

  const logout = $('#logoutBtn');
  if (logout) {
    logout.addEventListener('click', async () => {
      await api('php/login.php?action=logout');
      window.location.href = 'index.html';
    });
  }
}

// ---------- resource cards ----------
function resourceCard(r, user, { showStatus = false, admin = false } = {}) {
  const date = String(r.created_at).slice(0, 10);
  let actions = '';
  if (admin) {
    actions = `<button class="btn" data-id="${r.id}" data-decision="approve">Approve</button>
               <button class="btn danger" data-id="${r.id}" data-decision="reject">Reject</button>`;
  } else if (user) {
    actions = `<a class="btn" href="php/upload.php?action=download&id=${r.id}">Download PDF</a>`;
  } else {
    actions = `<a class="btn secondary" href="login.html">Log in to download</a>`;
  }
  return `
    <article class="res">
      <span class="tag">${esc(r.subject)}</span>
      <h3>${esc(r.title)}</h3>
      ${r.description ? `<p>${esc(r.description)}</p>` : ''}
      <p class="meta">By ${esc(r.uploader)} on ${esc(date)} &middot; ${Number(r.downloads)} downloads</p>
      ${showStatus ? `<span class="status ${esc(r.status)}">${esc(r.status)}</span>` : ''}
      <div class="actions">${actions}</div>
    </article>`;
}

// ---------- page logic ----------
function initHome() {
  $('#heroSearch').addEventListener('submit', (e) => {
    e.preventDefault();
    const q = new FormData(e.target).get('q');
    window.location.href = 'resources.html?q=' + encodeURIComponent(q || '');
  });
}

function initRegister() {
  $('#registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = await api('php/register.php', { method: 'POST', body: new FormData(e.target) });
    showMessage(data.message, data.ok);
    if (data.ok) setTimeout(() => (window.location.href = 'login.html'), 1200);
  });
}

function initLogin() {
  $('#loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = await api('php/login.php', { method: 'POST', body: new FormData(e.target) });
    showMessage(data.message, data.ok);
    if (data.ok) window.location.href = 'dashboard.html';
  });
}

function initUpload() {
  $('#uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    showMessage('Uploading...', true);
    const data = await api('php/upload.php', { method: 'POST', body: new FormData(form) });
    showMessage(data.message, data.ok);
    if (data.ok) form.reset();
  });
}

function initResources(user) {
  const form = $('#searchForm');
  const list = $('#list');
  const params = new URLSearchParams(window.location.search);
  form.q.value = params.get('q') || '';

  async function load() {
    const query = new URLSearchParams({ action: 'list', q: form.q.value, subject: form.subject.value });
    const data = await api('php/upload.php?' + query);
    if (!data.ok) { list.innerHTML = `<p class="empty">${esc(data.message)}</p>`; return; }

    // Fill the subject dropdown once
    if (form.subject.options.length === 1) {
      data.subjects.forEach((s) => form.subject.add(new Option(s, s)));
    }
    $('#count').textContent = `${data.resources.length} resource(s) found`;
    list.innerHTML = data.resources.length
      ? data.resources.map((r) => resourceCard(r, user)).join('')
      : '<p class="empty">No resources match your search yet.</p>';
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
  form.subject.addEventListener('change', load);
  load();
}

async function initDashboard(user) {
  $('#welcome').textContent = `Welcome, ${user.name}`;

  // My uploads
  const mine = await api('php/upload.php?action=mine');
  $('#list').innerHTML = mine.ok && mine.resources.length
    ? mine.resources.map((r) => resourceCard(r, user, { showStatus: true })).join('')
    : '<p class="empty">You have not uploaded anything yet. <a href="upload.html">Upload your first PDF</a>.</p>';

  // Admin only: approve or reject pending uploads
  if (user.role !== 'admin') return;
  $('#adminBox').hidden = false;
  const pending = $('#pendingList');

  async function loadPending() {
    const data = await api('php/upload.php?action=pending');
    pending.innerHTML = data.ok && data.resources.length
      ? data.resources.map((r) => resourceCard(r, user, { admin: true })).join('')
      : '<p class="empty">Nothing is waiting for approval.</p>';
  }

  pending.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    const body = new FormData();
    body.append('id', btn.dataset.id);
    body.append('decision', btn.dataset.decision);
    const data = await api('php/upload.php?action=review', { method: 'POST', body });
    showMessage(data.message, data.ok);
    loadPending();
  });
  loadPending();
}

// ---------- start ----------
document.addEventListener('DOMContentLoaded', async () => {
  const session = await api('php/login.php?action=session');
  const user = session.user || null;
  renderNav(user);

  const page = document.body.dataset.page;
  if (['dashboard', 'upload'].includes(page) && !user) {
    window.location.href = 'login.html';
    return;
  }

  if (page === 'home') initHome();
  if (page === 'register') initRegister();
  if (page === 'login') initLogin();
  if (page === 'upload') initUpload();
  if (page === 'resources') initResources(user);
  if (page === 'dashboard') initDashboard(user);
});
