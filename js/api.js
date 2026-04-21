// ═══════════════════════════════════════════════════════════════════
//  StudySync — api.js
//  Talks to PHP backend on same XAMPP server.
//  IMPORTANT: All pages must be opened via http://localhost/studysync/
//  NOT via file:// — that breaks sessions and fetch().
// ═══════════════════════════════════════════════════════════════════
// Auto-detect base path so this works wherever the folder is placed in htdocs
const _base = (() => {
   const folder = 'studysync';
   return `/${folder}/php`;
})();

console.log(_base)
// ── Low-level fetch wrapper ──────────────────────────────────────────
async function _req(url, opts = {}) {
   try {
       const res = await fetch(url, {
           credentials: 'include',          // ← sends PHP session cookie
           headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
           ...opts
       });
       const text = await res.text();
       try {
           return JSON.parse(text);
       } catch {
           console.error('Non-JSON from PHP:', text);
           return { success: false, message: 'Server error. Check PHP logs.' };
       }
   } catch (err) {
       console.error('Fetch error:', err);
       return { success: false, message: 'Cannot reach server. Is XAMPP running?' };
   }
}
// ── API object ───────────────────────────────────────────────────────
const API = {
   // Auth
   signup:        (d) => _req(`${_base}/auth.php?action=signup`,  { method:'POST', body: JSON.stringify(d) }),
   login:         (d) => _req(`${_base}/auth.php?action=login`,   { method:'POST', body: JSON.stringify(d) }),
   logout:        ()  => _req(`${_base}/auth.php?action=logout`),
   getMe:         ()  => _req(`${_base}/auth.php?action=me`),
   updateProfile: (d) => _req(`${_base}/auth.php?action=update`,  { method:'POST', body: JSON.stringify(d) }),
   changePassword: (d) => _req(`${_base}/auth.php?action=change_password`, { method:'POST', body: JSON.stringify(d) }),
   deleteAccount: ()  => _req(`${_base}/auth.php?action=delete`,  { method:'DELETE' }),
   // Tasks
   getTasks:    (p={}) => _req(`${_base}/tasks.php?action=list&${new URLSearchParams(p)}`),
   getStats:    ()     => _req(`${_base}/tasks.php?action=stats`),
   createTask:  (d)    => _req(`${_base}/tasks.php?action=create`, { method:'POST', body: JSON.stringify(d) }),
   updateTask:  (d)    => _req(`${_base}/tasks.php?action=update`, { method:'POST', body: JSON.stringify(d) }),
   toggleTask:  (id)   => _req(`${_base}/tasks.php?action=toggle`, { method:'POST', body: JSON.stringify({ id }) }),
   reorderTasks: (d) => _req(`${_base}/tasks.php?action=reorder`, { method:'POST', body: JSON.stringify(d) }),
   deleteTask:  (id)   => _req(`${_base}/tasks.php?action=delete&id=${id}`, { method:'DELETE' }),
   // ── Social & Gamification (New) ──────────────────
   getLeaderboard: () => _req(`${_base}/social.php?action=leaderboard`),
   addFriend: (searchTerm) => _req(`${_base}/social.php?action=add_friend`, { 
       method: 'POST', 
       body: JSON.stringify({ search: searchTerm }) 
   }),
   // AI
   askAI: (prompt) => _req(`${_base}/ai.php`, { method:'POST', body: JSON.stringify({ prompt }) }),
   // Notes
   getNotes: () => _req(`${_base}/notes.php?action=list`),
   saveNote: (d) => _req(`${_base}/notes.php?action=save`, { method: 'POST', body: JSON.stringify(d) }),
   starNote: (id) => _req(`${_base}/notes.php?action=star`, { method: 'POST', body: JSON.stringify({ id }) }),
   deleteNote: (id) => _req(`${_base}/notes.php?id=${id}`, { method: 'DELETE' })
};
// ── Toast notification ───────────────────────────────────────────────
function showToast(msg, type = 'success') {
   let wrap = document.getElementById('_toasts');
   if (!wrap) {
       wrap = document.createElement('div');
       wrap.id = '_toasts';
       wrap.style.cssText = 'position:fixed;top:80px;right:20px;zindex:999999;display:flex;flex-direction:column;gap:8px;pointer-events:none';
       document.body.appendChild(wrap);
   }
   const bg = { success:'#10b981', error:'#ef4444', info:'#6366f1' }[type] || '#6366f1';
   const icon = { success:'✓', error:'✕', info:'ℹ' }[type] || '•';
   const el = document.createElement('div');
   el.style.cssText = `background:${bg};color:#fff;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,0.15);display:flex;align-items:center;gap:8px;transform:translateX(80px);opacity:0;transition:all 0.3s ease;pointer-events:auto;`;
   el.innerHTML = `<span style="font-size:15px">${icon}</span> ${msg}`;
   wrap.appendChild(el);
   requestAnimationFrame(() => { el.style.transform = 'translateX(0)'; el.style.opacity = '1'; });
   setTimeout(() => {
       el.style.transform = 'translateX(80px)'; el.style.opacity = '0';
       setTimeout(() => el.remove(), 300);
   }, 3200);
}
// ── Auth guard — call at top of every protected page ────────────────
// Usage:  const user = await requireLogin();
async function requireLogin(redirectTo = 'index.html') {
   const res = await API.getMe();
   if (!res.success) {
       window.location.href = redirectTo;
       return null;
   }
   return res.data;
}
// ── Populate sidebar with logged-in user ─────────────────────────────
function populateSidebar(user) {
    if (!user) return;

    // 1. Update all Username labels (Side bar and Top Nav)
    const nameElements = document.querySelectorAll('.sidebar-username');
    nameElements.forEach(el => {
        el.innerText = user.full_name || "User";
    });

    // 2. Update all Avatar Initials
    const avatarElements = document.querySelectorAll('.sidebar-avatar');
    const initial = (user.full_name ? user.full_name.charAt(0) : "U").toUpperCase();
    avatarElements.forEach(el => {
        el.innerText = initial;
    });
}