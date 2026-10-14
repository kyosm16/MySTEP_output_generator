<?php
/**
 * draft.php  -  Menu gelongsor (drawer) dan sistem Draf untuk MySTEP Output Generator.
 *
 * Digunakan oleh index.php:
 *   require_once __DIR__ . '/draft.php';
 *   draft_styles();   // dalam <head>
 *   draft_markup();   // sebelum </body> (drawer, backdrop, toast)
 *   draft_script();   // selepas skrip utama
 *
 * Draf disimpan dalam pelayar pengguna (localStorage), bukan di pelayan.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Fail ini digunakan oleh index.php.');
}

function draft_styles(): void { ?>
    <style>
        /* ---------- Menu button (top left) ---------- */
        .menu-btn {
            width: 40px; height: 40px; flex: none; display: grid; place-items: center; cursor: pointer;
            border-radius: 13px; border: 1px solid rgba(255,255,255,.28); background: transparent; color: var(--nav-ink);
            transition: background .15s;
        }
        .menu-btn:hover { background: rgba(255,255,255,.12); }

        /* ---------- Drawer ---------- */
        .drawer-backdrop {
            display: none; position: fixed; inset: 0; z-index: 44;
            background: rgb(15 20 18 / .5); backdrop-filter: blur(3px);
        }
        .drawer-backdrop.show { display: block; }
        .drawer {
            position: fixed; top: 0; left: 0; bottom: 0; z-index: 45; width: min(340px, 88vw);
            background: var(--surface); color: var(--ink); border-right: 1px solid var(--line);
            border-radius: 0 var(--r-lg) var(--r-lg) 0; box-shadow: 0 30px 70px rgba(0,0,0,.35);
            transform: translateX(-105%); visibility: hidden;
            transition: transform .28s cubic-bezier(.2,.8,.2,1), visibility 0s linear .28s;
        }
        .drawer.open { transform: none; visibility: visible; transition: transform .28s cubic-bezier(.2,.8,.2,1), visibility 0s; }
        .drawer-view { display: flex; flex-direction: column; height: 100%; min-height: 0; }
        .drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; padding: 22px 20px 10px 24px; }
        .drawer-head h2 { font-family: var(--font-head); font-size: 24px; font-weight: 500; letter-spacing: -.01em; }
        .drawer-head p { font-size: 13px; color: var(--ink-soft); margin-top: 2px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .back-btn { background: none; border: 0; cursor: pointer; font-size: 14px; font-weight: 600; color: var(--brand-ink); padding: 6px 10px 6px 0; }
        .back-btn:hover { text-decoration: underline; text-underline-offset: 3px; }
        .drawer-title { padding: 0 24px 8px; }
        .drawer-title h2 { font-family: var(--font-head); font-size: 24px; font-weight: 500; letter-spacing: -.01em; }
        .drawer-title p { font-size: 13px; color: var(--ink-soft); }

        .drawer-nav { padding: 10px 14px; display: grid; gap: 4px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; width: 100%; text-align: left; cursor: pointer;
            background: none; border: 0; border-radius: 14px; padding: 13px 14px; font-size: 15px; font-weight: 600; color: var(--ink);
            transition: background .15s;
        }
        .nav-item:hover { background: var(--soft); }
        .nav-item svg { flex: none; color: var(--brand-ink); }
        .nav-item .grow { flex: 1; }
        .nav-item .chev { color: var(--ink-faint); }
        .badge {
            min-width: 24px; height: 24px; padding: 0 8px; border-radius: 999px; display: inline-grid; place-items: center;
            background: var(--brand-tint); color: var(--brand-ink); font-size: 12.5px; font-weight: 700;
        }

        .draft-list { flex: 1; overflow-y: auto; padding: 6px 12px 10px; min-height: 80px; }
        .draft { display: flex; align-items: center; gap: 4px; border-radius: 12px; padding: 2px 4px 2px 2px; }
        .draft + .draft { margin-top: 2px; }
        .draft:hover { background: var(--soft); }
        .draft.active { background: var(--brand-tint); }
        .draft-open { flex: 1; min-width: 0; text-align: left; background: none; border: 0; padding: 10px; cursor: pointer; border-radius: 10px; }
        .draft-title { display: block; font-size: 14px; font-weight: 600; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .draft.active .draft-title { color: var(--brand-ink); }
        .draft-meta { display: block; font-size: 12px; color: var(--ink-soft); margin-top: 1px; }
        .draft-empty { font-size: 13.5px; color: var(--ink-soft); padding: 12px 10px; }
        .drawer-note { font-size: 12px; color: var(--ink-faint); padding: 12px 24px 18px; border-top: 1px solid var(--line); }

        .save-status { margin-top: 8px; font-size: 12.5px; color: var(--ink-faint); text-align: center; }
        .save-status.dirty { color: var(--accent); }

        .toast {
            position: fixed; left: 50%; bottom: 28px; transform: translate(-50%, 24px); opacity: 0; pointer-events: none;
            background: var(--nav-bg); color: var(--nav-ink); padding: 11px 20px; border-radius: 999px;
            font-size: 14px; font-weight: 500; box-shadow: 0 14px 34px rgba(0,0,0,.28); z-index: 60;
            transition: opacity .2s, transform .2s;
        }
        .toast.show { opacity: 1; transform: translate(-50%, 0); }

        @media (prefers-reduced-motion: reduce) { .drawer { transition: none !important; } }
    </style>
<?php }

function draft_markup(): void { ?>
    <div class="drawer-backdrop no-print" id="drawerBackdrop" onclick="closeDrawer()"></div>

    <aside class="drawer no-print" id="drawer" aria-label="Menu utama" inert>
        <!-- Paparan 1: menu -->
        <div class="drawer-view" data-view="menu">
            <div class="drawer-head">
                <div>
                    <h2>Menu</h2>
                    <p id="drawerCurrent">Belum disimpan</p>
                </div>
                <button type="button" class="icon-btn" onclick="closeDrawer()" aria-label="Tutup menu">✕</button>
            </div>
            <nav class="drawer-nav">
                <button type="button" class="nav-item" onclick="newDraft()">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span class="grow">Borang baharu</span>
                </button>
                <button type="button" class="nav-item" onclick="showView('drafts')">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
                    <span class="grow">Draf saya</span>
                    <span class="badge" id="draftCount">0</span>
                    <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </nav>
        </div>

        <!-- Paparan 2: senarai draf -->
        <div class="drawer-view hidden" data-view="drafts">
            <div class="drawer-head">
                <button type="button" class="back-btn" onclick="showView('menu')">← Menu</button>
                <button type="button" class="icon-btn" onclick="closeDrawer()" aria-label="Tutup menu">✕</button>
            </div>
            <div class="drawer-title">
                <h2>Draf saya</h2>
                <p>Klik pada draf untuk membukanya.</p>
            </div>
            <div class="draft-list" id="draftList"></div>
            <p class="drawer-note">Draf disimpan dalam pelayar ini sahaja. Jika data pelayar dipadam, draf turut hilang.</p>
        </div>
    </aside>

    <div class="toast no-print" id="toast" role="status" aria-live="polite"></div>
<?php }

function draft_script(): void { ?>
    <script>
        // ---------- Draf (disimpan dalam pelayar / localStorage) ----------
        const DRAFTS_KEY = 'mystep-drafts';
        const ACTIVE_KEY = 'mystep-active';
        const BULAN_MY = ['Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
        let activeDraftId = null, dirty = false, restoring = false, toastTimer = null;

        function readDrafts() {
            try { const v = JSON.parse(localStorage.getItem(DRAFTS_KEY) || '[]'); return Array.isArray(v) ? v : []; }
            catch (e) { return []; }
        }
        function writeDrafts(list) {
            try { localStorage.setItem(DRAFTS_KEY, JSON.stringify(list)); return true; }
            catch (e) { return false; }
        }
        function setActive(id) {
            activeDraftId = id;
            try { if (id) localStorage.setItem(ACTIVE_KEY, id); else localStorage.removeItem(ACTIVE_KEY); } catch (e) {}
            updateCurrent();
        }

        function toast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => t.classList.remove('show'), 2400);
        }

        function fmtDate(ts) {
            return new Date(ts).toLocaleString('ms-MY', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
        }

        function setStatus(text, isDirty) {
            const el = document.getElementById('saveStatus');
            if (!el) return;
            el.textContent = text;
            el.classList.toggle('dirty', !!isDirty);
        }

        function updateCurrent() {
            const el = document.getElementById('drawerCurrent');
            if (!el) return;
            const d = readDrafts().find(x => x.id === activeDraftId);
            el.textContent = d ? d.name : 'Belum disimpan';
        }

        function markDirty() {
            if (restoring) return;
            dirty = true;
            setStatus('Perubahan belum disimpan', true);
        }

        // ----- Kumpul / isi semula data borang -----
        function collectForm() {
            const f = document.getElementById('mainForm');
            const val = n => (f.elements[n] ? f.elements[n].value : '');
            const data = {
                nama: val('nama'), kp: val('kp'), unit: val('unit'),
                bulan_nama: val('bulan_nama'), tahun: val('tahun'), upah_kasar: val('upah_kasar'),
                tasks: []
            };
            document.querySelectorAll('.task-row').forEach(row => {
                const id = row.id.replace('task-row-', '');
                const g = field => { const el = row.querySelector(`[name="tasks[${id}][${field}]"]`); return el ? el.value : ''; };
                const items = type => [...row.querySelectorAll(`#${type}-container-${id} .item-row`)].map(r => {
                    const i = r.querySelectorAll('input');
                    return { desc: i[0].value, val: i[1].value, unit: i[2].value };
                });
                data.tasks.push({
                    jenis: g('jenis'), jenis_custom: g('jenis_custom'), deskripsi: g('deskripsi'),
                    sasaran_mode: g('sasaran_mode'), sasaran_items: items('sasaran'),
                    sasaran_text: g('sasaran_text'), sasaran_qty: g('sasaran_manual_qty'), sasaran_unit: g('sasaran_manual_unit'),
                    pencapaian_mode: g('pencapaian_mode'), pencapaian_items: items('pencapaian'),
                    pencapaian_text: g('pencapaian_text'), pencapaian_qty: g('pencapaian_manual_qty'), pencapaian_unit: g('pencapaian_manual_unit'),
                    nota: g('nota'), justifikasi: g('justifikasi')
                });
            });
            return data;
        }

        function autoName(data) {
            const m = BULAN_MY[(parseInt(data.bulan_nama, 10) || 1) - 1] || '';
            const bulan = (m + ' ' + (data.tahun || '')).trim();
            return data.nama ? `${bulan} — ${data.nama}` : (bulan || 'Draf tanpa nama');
        }

        function fillTask(id, t) {
            const row = document.getElementById('task-row-' + id);
            const set = (field, v) => { const el = row.querySelector(`[name="tasks[${id}][${field}]"]`); if (el && v != null) el.value = v; };
            set('jenis', t.jenis);
            handleTugasanChange(id);
            set('jenis_custom', t.jenis_custom);
            set('deskripsi', t.deskripsi);
            set('justifikasi', t.justifikasi);
            set('nota', t.nota);

            ['sasaran', 'pencapaian'].forEach((type, idx) => {
                const container = document.getElementById(`${type}-container-${id}`);
                container.innerHTML = '';
                const items = (t[type + '_items'] && t[type + '_items'].length) ? t[type + '_items'] : [{ desc: '', val: '', unit: 'set' }];
                items.forEach(it => {
                    if (type === 'sasaran') addSasaranItem(id); else addPencapaianItem(id);
                    const inputs = container.lastElementChild.querySelectorAll('input');
                    inputs[0].value = it.desc; inputs[1].value = it.val; inputs[2].value = it.unit;
                });
                set(type + '_text', t[type + '_text']);
                set(type + '_manual_qty', t[type + '_qty']);
                set(type + '_manual_unit', t[type + '_unit']);
                if (t[type + '_mode'] === 'text') {
                    const btn = row.querySelectorAll('.seg')[idx].querySelectorAll('button')[1];
                    toggleOutputMode(id, type, 'text', btn);
                }
            });
        }

        function applyData(data) {
            const f = document.getElementById('mainForm');
            ['nama', 'kp', 'unit', 'bulan_nama', 'tahun', 'upah_kasar'].forEach(n => {
                if (f.elements[n] && data[n] != null && data[n] !== '') f.elements[n].value = data[n];
            });
            document.getElementById('tasksWrapper').innerHTML = '';
            const tasks = (data.tasks && data.tasks.length) ? data.tasks : [null];
            tasks.forEach(t => {
                addTaskRow();
                if (t) fillTask(taskCounter, t);
            });
            calculateAllTasks();
            reindexBil();
            updateSummary();
        }

        function finishRestore() {
            setTimeout(() => { restoring = false; dirty = false; }, 0);
        }

        // ----- Simpan / buka / padam -----
        function saveDraft(silent) {
            const data = collectForm();
            const custom = document.getElementById('draftName').value.trim();
            const name = custom || autoName(data);
            const list = readDrafts();
            let d = list.find(x => x.id === activeDraftId);
            if (d) {
                d.name = name; d.custom = !!custom; d.data = data; d.updated = Date.now();
            } else {
                d = { id: 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), name: name, custom: !!custom, data: data, updated: Date.now() };
                list.unshift(d);
            }
            if (!writeDrafts(list)) {
                toast('Draf tidak dapat disimpan: storan pelayar tidak tersedia.');
                return false;
            }
            setActive(d.id);
            dirty = false;
            renderDrafts();
            setStatus('Disimpan ' + fmtDate(d.updated), false);
            if (!silent) toast('Draf disimpan');
            return true;
        }

        function loadDraft(id, force) {
            const d = readDrafts().find(x => x.id === id);
            if (!d) return;
            if (!force && dirty && !confirm('Perubahan semasa belum disimpan. Buka draf lain juga?')) return;
            restoring = true;
            applyData(d.data);
            setActive(id);
            document.getElementById('draftName').value = d.custom ? d.name : '';
            renderDrafts();
            setStatus('Disimpan ' + fmtDate(d.updated), false);
            finishRestore();
            closeDrawer();
            if (!force) toast('Draf dibuka');
        }

        function newDraft() {
            if (dirty && !confirm('Perubahan semasa belum disimpan. Mulakan borang baharu juga?')) return;
            restoring = true;
            document.getElementById('mainForm').reset();
            document.getElementById('tasksWrapper').innerHTML = '';
            addTaskRow();
            setActive(null);
            document.getElementById('draftName').value = '';
            renderDrafts();
            setStatus('Belum disimpan', false);
            finishRestore();
            closeDrawer();
        }

        function deleteDraft(id, ev) {
            ev.stopPropagation();
            if (!confirm('Padam draf ini? Tindakan ini tidak boleh dibatalkan.')) return;
            writeDrafts(readDrafts().filter(d => d.id !== id));
            if (id === activeDraftId) {
                setActive(null);
                setStatus('Belum disimpan', false);
            }
            renderDrafts();
            updateCurrent();
            toast('Draf dipadam');
        }

        function renderDrafts() {
            const wrap = document.getElementById('draftList');
            const list = readDrafts().sort((a, b) => b.updated - a.updated);
            document.getElementById('draftCount').textContent = list.length;
            wrap.innerHTML = '';
            if (!list.length) {
                const p = document.createElement('p');
                p.className = 'draft-empty';
                p.textContent = 'Belum ada draf. Isi borang, kemudian tekan Simpan draf.';
                wrap.appendChild(p);
                return;
            }
            list.forEach(d => {
                const item = document.createElement('div');
                item.className = 'draft' + (d.id === activeDraftId ? ' active' : '');

                const open = document.createElement('button');
                open.type = 'button';
                open.className = 'draft-open';
                const title = document.createElement('span');
                title.className = 'draft-title';
                title.textContent = d.name;
                const meta = document.createElement('span');
                meta.className = 'draft-meta';
                const n = (d.data && d.data.tasks) ? d.data.tasks.length : 0;
                meta.textContent = `${n} tugasan · ${fmtDate(d.updated)}`;
                open.appendChild(title);
                open.appendChild(meta);
                open.addEventListener('click', () => loadDraft(d.id));

                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'icon-btn';
                del.setAttribute('aria-label', 'Padam draf ' + d.name);
                del.textContent = '✕';
                del.addEventListener('click', ev => deleteDraft(d.id, ev));

                item.appendChild(open);
                item.appendChild(del);
                wrap.appendChild(item);
            });
        }

        // ----- Drawer (menu gelongsor dari kiri) -----
        function showView(name) {
            document.querySelectorAll('.drawer-view').forEach(v => v.classList.toggle('hidden', v.dataset.view !== name));
            const first = document.querySelector(`.drawer-view[data-view="${name}"] button`);
            if (first) first.focus();
        }
        function isDrawerOpen() {
            return document.getElementById('drawer').classList.contains('open');
        }
        function openDrawer() {
            const drawer = document.getElementById('drawer');
            drawer.inert = false;
            drawer.classList.add('open');
            document.getElementById('drawerBackdrop').classList.add('show');
            document.getElementById('menuBtn').setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            updateCurrent();
            showView('menu');
        }
        function closeDrawer() {
            if (!isDrawerOpen()) return;
            const drawer = document.getElementById('drawer');
            drawer.classList.remove('open');
            drawer.inert = true;
            document.getElementById('drawerBackdrop').classList.remove('show');
            const btn = document.getElementById('menuBtn');
            btn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            btn.focus();
        }
        function toggleDrawer() {
            if (isDrawerOpen()) closeDrawer(); else openDrawer();
        }

        function initDrafts() {
            const form = document.getElementById('mainForm');
            form.addEventListener('input', markDirty);
            form.addEventListener('change', markDirty);
            form.addEventListener('submit', () => { saveDraft(true); });
            new MutationObserver(markDirty).observe(document.getElementById('tasksWrapper'), { childList: true, subtree: true });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

            renderDrafts();
            let id = null;
            try { id = localStorage.getItem(ACTIVE_KEY); } catch (e) {}
            if (id && readDrafts().some(d => d.id === id)) {
                loadDraft(id, true);
            } else {
                restoring = true;
                setActive(null);
                addTaskRow();
                finishRestore();
            }
        }
    </script>
<?php }