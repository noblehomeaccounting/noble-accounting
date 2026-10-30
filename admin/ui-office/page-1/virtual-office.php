<?php
// virtual-office.php  —  Virtual Office (3D): parang game na opisina — bawat department may kuwarto, makikita kung sino ang naka-online
// Three.js (low-poly / voxel na tao). Online = nakaupo at nagta-type (minsan tumatayo at naglalakad). Offline = bakanteng upuan.
// Online galing sa Supabase Presence (sidebar → NH_RT.onPresence) — agad; fallback: noblerole.last_active bawat 15s.
// Basa lang. Para sa lahat ng naka-login na admin account.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$voAjaxUrl = BASE_URL . '/virtualofficeajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Office</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <script type="importmap">
        { "imports": {
            "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
            "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
        } }
    </script>
    <style>
        .vo-hazard {
            background: repeating-linear-gradient(-45deg, #f59e0b 0 12px, #1f2937 12px 24px);
        }

        #voStage {
            position: relative;
            height: clamp(420px, 68vh, 760px);
            border-radius: 18px;
            overflow: hidden;
            background: radial-gradient(120% 90% at 50% 0%, #fef3c7 0%, #e0f2fe 45%, #cbd5e1 100%);
        }

        #voStage canvas {
            display: block;
            outline: none;
            touch-action: none;
        }

        #voLabels {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .vo-tag {
            transform: translateY(-6px);
            padding: 2px 7px 2px 5px;
            border-radius: 999px;
            background: rgba(17, 24, 39, .86);
            color: #fff;
            font: 700 10.5px/1.3 ui-sans-serif, system-ui, sans-serif;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .25);
            transition: opacity .2s;
        }

        .vo-tag i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, .35);
        }

        .vo-tag.me {
            background: #b45309;
        }

        .vo-tag.hl {
            background: #f59e0b;
            color: #111827;
        }

        .vo-tag.far {
            opacity: 0;
        }

        .vo-tag.far.me,
        .vo-tag.far.hl {
            opacity: 1;
        }

        .vo-bubble {
            transform: translateY(-30px);
            font-size: 18px;
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, .25));
        }

        .vo-sign {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 8px;
            color: #fff;
            font: 800 11px/1.2 ui-sans-serif, system-ui, sans-serif;
            letter-spacing: .02em;
            white-space: nowrap;
            box-shadow: 0 3px 0 rgba(0, 0, 0, .3);
            pointer-events: auto;
            cursor: pointer;
        }

        .vo-sign b {
            background: rgba(0, 0, 0, .28);
            border-radius: 999px;
            padding: 1px 6px;
            font-size: 10px;
        }

        .vo-sign.far .nm {
            display: none;
        }

        .vo-sign.far {
            padding: 3px 7px;
            gap: 4px;
        }

        .vo-ctrl {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: rgba(255, 255, 255, .92);
            color: #374151;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
        }

        .vo-ctrl:hover {
            color: #b45309;
            border-color: #f59e0b;
        }

        .vo-row[data-active="1"] {
            background: #fffbeb;
            box-shadow: inset 3px 0 0 #b45309;
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content"
        class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300 overflow-x-hidden">
        <!-- Header -->
        <section class="relative overflow-hidden rounded-2xl mb-4 bg-gray-900 text-white shadow-lg">
            <div class="h-1.5 vo-hazard"></div>
            <div class="absolute -right-10 -top-10 w-56 h-56 rounded-full bg-amber-500/20 blur-2xl pointer-events-none">
            </div>
            <div class="relative p-4 md:p-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span
                        class="w-12 h-12 rounded-2xl bg-amber-500 text-gray-900 flex items-center justify-center shrink-0 shadow-[0_4px_0_#92400e]"><i
                            class="fa-solid fa-building-user text-xl"></i></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-400">NobleHome HQ</p>
                        <h1 class="text-xl md:text-2xl font-extrabold leading-tight">Virtual Office</h1>
                        <p class="text-xs text-gray-400">Drag to rotate · scroll or pinch to zoom · tap anyone to see
                            who they are.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-3 rounded-2xl bg-white/10 border border-white/10 px-4 py-2.5">
                        <span class="relative flex w-3 h-3"><span
                                class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span><span
                                class="relative inline-flex rounded-full w-3 h-3 bg-green-500"></span></span>
                        <span class="text-2xl font-extrabold tabular-nums leading-none" id="voOnline">0</span>
                        <span class="text-[11px] text-gray-300 leading-tight">online<br>of <b id="voTotal"
                                class="text-white">0</b></span>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] text-gray-300 bg-white/5 border border-white/10 rounded-full px-3 py-1.5"><span
                            id="voLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span
                            id="voLiveText">Auto-refresh</span><span class="text-white/30">·</span><span
                            id="voUpdated">Loading…</span></span>
                </div>
            </div>
        </section>

        <div class="grid xl:grid-cols-[minmax(0,1fr)_320px] gap-4 items-start">
            <!-- 3D -->
            <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-2 min-w-0">
                <div id="voStage">
                    <div id="voLabels"></div>
                    <div id="voLoading"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-xs font-semibold text-gray-500">
                        <i class="fa-solid fa-helmet-safety text-2xl text-amber-600 animate-bounce"></i>Building the
                        office…</div>
                    <div id="vo3dFail"
                        class="hidden absolute inset-0 flex-col items-center justify-center gap-2 text-center p-6 text-xs text-gray-500">
                        <i class="fa-solid fa-cube text-2xl text-gray-300"></i>
                        <p class="font-semibold text-gray-700">3D view is not available on this device or connection.
                        </p>
                        <p>The list on the side still shows who is online.</p>
                    </div>
                    <div class="absolute right-3 top-3 flex flex-col gap-1.5" id="voCtrls">
                        <button type="button" class="vo-ctrl" data-cam="in" aria-label="Zoom in"><i
                                class="fa-solid fa-plus"></i></button>
                        <button type="button" class="vo-ctrl" data-cam="out" aria-label="Zoom out"><i
                                class="fa-solid fa-minus"></i></button>
                        <button type="button" class="vo-ctrl" data-cam="rot" aria-label="Rotate view"><i
                                class="fa-solid fa-rotate"></i></button>
                        <button type="button" class="vo-ctrl" data-cam="home" aria-label="Show whole office"><i
                                class="fa-solid fa-expand"></i></button>
                    </div>
                    <div class="absolute left-3 bottom-3 right-3 sm:right-14 flex flex-nowrap sm:flex-wrap gap-1.5 overflow-x-auto sm:overflow-visible pointer-events-auto sm:pointer-events-none [scrollbar-width:none] pb-0.5"
                        id="voLegend"></div>
                </div>
            </section>

            <!-- Listahan -->
            <aside
                class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden min-w-0 xl:sticky xl:top-4">
                <div class="p-3 border-b border-gray-100 space-y-2">
                    <div class="relative"><i
                            class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                        <input type="text" id="voSearch" placeholder="Find someone…"
                            class="w-full h-9 bg-gray-50 border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                    </div>
                    <div class="flex gap-2">
                        <select id="voBranch"
                            class="hidden flex-1 h-8 bg-white border border-gray-200 rounded-lg px-2 text-[11px] outline-none focus:border-amber-500"></select>
                        <label
                            class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-gray-200 text-[11px] font-semibold text-gray-600 cursor-pointer select-none whitespace-nowrap">
                            <input type="checkbox" id="voShowOff" class="accent-amber-700" checked> Empty desks
                        </label>
                    </div>
                </div>
                <div id="voList"
                    class="max-h-[60vh] xl:max-h-[calc(68vh-40px)] overflow-y-auto overscroll-contain [scrollbar-width:thin]">
                </div>
            </aside>
        </div>
        <p class="text-[11px] text-gray-400 mt-3"><i class="fa-solid fa-circle-info mr-1"></i>Live: people appear the
            moment they open the system and leave when they close it. If live is off, it checks every 15 seconds.</p>
    </main>

    <!-- Detalye ng tao -->
    <div id="voCard" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
            <div class="h-1.5 vo-hazard"></div>
            <div class="flex justify-end px-3 pt-3"><button type="button" id="voCardClose" aria-label="Close"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100"><i
                        class="fa-solid fa-xmark text-base leading-none"></i></button></div>
            <div id="voCardBody" class="px-6 pb-6 -mt-4"></div>
        </div>
    </div>

    <script>
        // ═══ Data + listahan (gumagana kahit walang 3D) ═══
        window.VO = (function () {
            'use strict';
            const AJAX = <?= json_encode($voAjaxUrl) ?>, MEMO = 'nh-vo-';
            const F = id => document.getElementById(id);
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const memo = {
                get: k => { try { return localStorage.getItem(MEMO + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem(MEMO + k, v); } catch (e) { } },
            };
            // [key, pangalan, icon, kulay, sahig, field (hard hat)]
            const ROOMS = [
                ['superadmin', 'Executive Office', 'fa-crown', '#1f2937', '#d6d3d1', false],
                ['accounting', 'Accounting', 'fa-calculator', '#16a34a', '#bbf7d0', false],
                ['sales', 'Sales & Marketing', 'fa-handshake', '#dc2626', '#fecaca', false],
                ['designer', 'Design Studio', 'fa-compass-drafting', '#0d9488', '#99f6e4', false],
                ['cutting', 'Cutting & Woodworks', 'fa-tree', '#ca8a04', '#fde68a', true],
                ['po', 'Purchasing', 'fa-cart-flatbed', '#4f46e5', '#c7d2fe', false],
                ['pc', 'Project Coordination', 'fa-helmet-safety', '#0891b2', '#a5f3fc', true],
                ['operations', 'Operations', 'fa-person-digging', '#ea580c', '#fed7aa', true],
                ['graphic', 'Graphic Design', 'fa-palette', '#db2777', '#fbcfe8', false],
                ['hr', 'Human Resources', 'fa-users', '#9333ea', '#e9d5ff', false],
                ['it', 'IT Department', 'fa-laptop-code', '#2563eb', '#bfdbfe', false],
                ['lobby', 'Lobby', 'fa-door-open', '#6b7280', '#e5e7eb', false],
            ];
            const ROOM = Object.fromEntries(ROOMS.map(r => [r[0], { key: r[0], name: r[1], icon: r[2], accent: r[3], floor: r[4], field: r[5] }]));
            const ago = s => s === null ? 'Never logged in' : s < 60 ? 'Active now' : s < 3600 ? `Last seen ${Math.floor(s / 60)} min ago` : s < 86400 ? `Last seen ${Math.floor(s / 3600)} hr ago` : `Last seen ${Math.floor(s / 86400)} day${s >= 172800 ? 's' : ''} ago`;
            const initials = n => String(n || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
            const cap = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';

            let people = [], lastSig = '', active = 0;
            let rtPresence = {}, rtReady = false;
            const subs = [];     // 3D: tatawagin kapag may bagong listahan
            const sigOf = () => JSON.stringify(visible().map(p => [p.id, p.online, p.room, p.name])) + '|' + F('voShowOff').checked;
            function visible() { const br = F('voBranch').value; return people.filter(p => !br || p.branch === br); }
            function applyPresence() {
                if (!rtReady) return;
                people.forEach(p => {
                    const was = p.online;
                    p.online = !!rtPresence[p.id];
                    if (p.online) p.secs = 0;
                    else if (was && (p.secs === null || p.secs < 60)) p.secs = 60;
                });
            }

            function avatarDot(p, size = 'w-8 h-8 text-[11px]') {
                const room = ROOM[p.room] || ROOM.lobby;
                return `<span class="relative ${size} shrink-0 rounded-xl flex items-center justify-center font-bold text-white shadow-[0_2px_0_rgba(0,0,0,.25)]" style="background:${room.accent}">
                    ${room.field ? '<i class="fa-solid fa-helmet-safety absolute -top-2 left-1/2 -translate-x-1/2 text-[11px] text-yellow-400 drop-shadow"></i>' : ''}${esc(initials(p.name))}
                    ${p.online ? '<span class="absolute -right-0.5 -bottom-0.5 w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-white"></span>' : ''}</span>`;
            }
            function renderList() {
                const q = (F('voSearch').value || '').trim().toLowerCase();
                const list = visible();
                F('voOnline').textContent = list.filter(p => p.online).length;
                F('voTotal').textContent = list.length;
                const match = p => !q || p.name.toLowerCase().includes(q) || (ROOM[p.room] || ROOM.lobby).name.toLowerCase().includes(q);
                const on = list.filter(p => p.online && match(p)), off = list.filter(p => !p.online && match(p));
                const row = p => {
                    const room = ROOM[p.room] || ROOM.lobby;
                    return `<button type="button" data-person="${p.id}" data-active="${p.id === active ? 1 : 0}" class="vo-row w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-gray-50 ${p.online ? '' : 'opacity-70'}">
                        ${avatarDot(p)}
                        <span class="min-w-0 flex-1"><span class="block text-[12.5px] font-semibold text-gray-900 truncate">${esc(p.name)}${p.me ? ' <span class="text-[10px] font-bold text-amber-700">YOU</span>' : ''}</span>
                            <span class="block text-[10.5px] text-gray-400 truncate">${esc(room.name)}${p.position ? ' · ' + esc(cap(p.position)) : ''}</span></span>
                        <span class="text-[10px] font-semibold shrink-0 ${p.online ? 'text-green-600' : 'text-gray-400'}">${p.online ? 'Online' : esc(ago(p.secs).replace('Last seen ', ''))}</span></button>`;
                };
                const head = (t, n, c) => `<p class="sticky top-0 z-10 bg-white/95 backdrop-blur px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider ${c} border-b border-gray-100">${t} <span class="text-gray-400">${n}</span></p>`;
                F('voList').innerHTML = (on.length || off.length)
                    ? head('Online now', on.length, 'text-green-700') + (on.map(row).join('') || '<p class="px-3 py-4 text-[11px] text-gray-400">Nobody online.</p>')
                    + (off.length ? head('Offline', off.length, 'text-gray-500') + off.map(row).join('') : '')
                    : '<p class="px-3 py-10 text-center text-[11px] text-gray-400">No one matches.</p>';
                // legend sa 3D
                const byRoom = {};
                list.forEach(p => { const r = byRoom[p.room] = byRoom[p.room] || [0, 0]; r[1]++; if (p.online) r[0]++; });
                F('voLegend').innerHTML = ROOMS.filter(r => byRoom[r[0]]).map(r => `<button type="button" data-room-go="${r[0]}" class="pointer-events-auto shrink-0 inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-white/90 border border-gray-200 text-[10.5px] font-semibold text-gray-700 hover:border-amber-400 shadow-sm">
                    <span class="w-2.5 h-2.5 rounded-sm" style="background:${r[3]}"></span>${esc(r[1])}<span class="text-gray-400 tabular-nums">${byRoom[r[0]][0]}/${byRoom[r[0]][1]}</span></button>`).join('');
            }
            function emit() { renderList(); const sig = sigOf(); if (sig !== lastSig) { lastSig = sig; subs.forEach(cb => { try { cb(); } catch (e) { console.error(e); } }); } }

            // ── Card ──
            function openCard(id) {
                const p = people.find(x => x.id === id); if (!p) return;
                const room = ROOM[p.room] || ROOM.lobby;
                F('voCardBody').innerHTML = `
                    <div class="flex flex-col items-center text-center">
                        <div class="mb-3">${avatarDot(p, 'w-16 h-16 text-xl rounded-2xl')}</div>
                        <p class="text-base font-bold text-gray-900 break-words">${esc(p.name)}${p.me ? ' <span class="text-xs font-semibold text-amber-700">(You)</span>' : ''}</p>
                        <p class="text-xs text-gray-500 mt-0.5"><i class="fa-solid ${room.icon} mr-1" style="color:${room.accent}"></i>${esc(room.key === 'lobby' ? (p.role || 'Staff') : room.name)}${p.position ? ' · ' + esc(cap(p.position)) : ''}</p>
                        ${p.branch ? `<p class="text-[11px] text-gray-400 mt-0.5"><i class="fa-solid fa-building mr-1"></i>${esc(p.branch)} branch</p>` : ''}
                        <span class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold ${p.online ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-500 border border-gray-200'}">
                            <span class="w-2 h-2 rounded-full ${p.online ? 'bg-green-500 animate-pulse' : 'bg-gray-400'}"></span>${esc(ago(p.secs))}</span>
                    </div>`;
                F('voCard').classList.remove('hidden'); F('voCard').classList.add('flex');
            }
            const closeCard = () => { F('voCard').classList.add('hidden'); F('voCard').classList.remove('flex'); };
            F('voCardClose').addEventListener('click', closeCard);
            F('voCard').addEventListener('click', e => { if (e.target === F('voCard')) closeCard(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCard(); });

            // ── Kunin ang listahan ──
            async function load() {
                try {
                    const r = await fetch(AJAX, { credentials: 'same-origin', cache: 'no-store' });
                    const j = await r.json();
                    if (!j.success) throw new Error(j.message || 'Could not load.');
                    F('voUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                    people = j.people;
                    applyPresence();
                    const branches = [...new Set(people.map(p => p.branch).filter(Boolean))].sort();
                    const sel = F('voBranch');
                    if (branches.length > 1 && sel.options.length !== branches.length + 1) {
                        const cur = sel.value || memo.get('branch') || '';
                        sel.innerHTML = '<option value="">All branches</option>' + branches.map(b => `<option value="${esc(b)}">${esc(b)}</option>`).join('');
                        sel.value = branches.includes(cur) ? cur : '';
                        sel.classList.remove('hidden');
                    }
                    emit();
                } catch (e) {
                    F('voUpdated').textContent = 'Offline — retrying';
                    if (!people.length) F('voList').innerHTML = `<p class="px-3 py-10 text-center text-[11px] text-red-600">${esc(e.message)}</p>`;
                }
            }
            let poll = null;
            function startPoll() { if (poll) clearInterval(poll); poll = setInterval(() => { if (!document.hidden) load(); }, rtReady ? 60000 : 15000); }
            startPoll();
            document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
            (function hookPresence() {
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.onPresence || hookPresence.done) return;
                    hookPresence.done = true;
                    let t = null;
                    window.NH_RT.onPresence((map, ready) => {
                        const wasReady = rtReady;
                        rtPresence = map || {}; rtReady = !!ready;
                        F('voLiveText').textContent = rtReady ? 'Live' : 'Auto-refresh';
                        F('voLiveDot').className = 'w-2 h-2 rounded-full ' + (rtReady ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        if (wasReady !== rtReady) startPoll();
                        if (!rtReady) { load(); return; }
                        clearTimeout(t);
                        t = setTimeout(() => { if (people.length) { applyPresence(); emit(); } }, 250);
                    });
                };
                hook();
                if (!hookPresence.done) document.addEventListener('DOMContentLoaded', hook);
            })();

            F('voSearch').addEventListener('input', () => { renderList(); api.onSearch && api.onSearch(); });
            F('voBranch').addEventListener('change', () => { memo.set('branch', F('voBranch').value); emit(); });
            F('voShowOff').checked = memo.get('off') !== '0';
            F('voShowOff').addEventListener('change', () => { memo.set('off', F('voShowOff').checked ? '1' : '0'); emit(); });
            document.addEventListener('click', e => {
                const r = e.target.closest('[data-person]');
                if (r && r.closest('#voList')) { const id = +r.dataset.person; active = id; renderList(); if (api.focusPerson && api.focusPerson(id)) setTimeout(() => openCard(id), 650); else openCard(id); return; }
                const g = e.target.closest('[data-room-go]');
                if (g && api.focusRoom) api.focusRoom(g.dataset.roomGo);
            });
            const api = {
                ROOMS, ROOM, F, esc, openCard, visible, memo,
                people: () => people, showOff: () => F('voShowOff').checked, query: () => (F('voSearch').value || '').trim().toLowerCase(),
                onChange(cb) { subs.push(cb); if (people.length) cb(); },
                setActive(id) { active = id; renderList(); },
            };
            load();
            return api;
        })();
    </script>

    <script type="module">
        // ═══ 3D OFFICE (Three.js) — gusali na may hallway, pinto, lobby, pantry at meeting room ═══
        const VO = window.VO, F = VO.F;
        let THREE, OrbitControls, CSS2DRenderer, CSS2DObject;
        try {
            THREE = await import('three');
            ({ OrbitControls } = await import('three/addons/controls/OrbitControls.js'));
            ({ CSS2DRenderer, CSS2DObject } = await import('three/addons/renderers/CSS2DRenderer.js'));
            const c = document.createElement('canvas');
            if (!(c.getContext('webgl2') || c.getContext('webgl'))) throw new Error('no webgl');
        } catch (e) {
            console.warn('Virtual Office 3D:', e);
            F('voLoading').classList.add('hidden'); F('vo3dFail').classList.remove('hidden'); F('vo3dFail').classList.add('flex'); F('voCtrls').classList.add('hidden');
            throw e;
        }
        const V3 = THREE.Vector3;

        // ── Renderer / camera / ilaw ──
        const stage = F('voStage'), labelsEl = F('voLabels');
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        stage.prepend(renderer.domElement);
        const labelR = new CSS2DRenderer({ element: labelsEl });
        const scene = new THREE.Scene();
        const cam = new THREE.OrthographicCamera(-10, 10, 10, -10, 1, 400);
        const controls = new OrbitControls(cam, renderer.domElement);
        controls.enableDamping = true; controls.dampingFactor = 0.08;
        controls.minPolarAngle = 0.45; controls.maxPolarAngle = 1.2;
        controls.minZoom = 0.25; controls.maxZoom = 4.5;
        controls.screenSpacePanning = false;
        scene.add(new THREE.HemisphereLight(0xffffff, 0x8fa3b8, 1.1));
        const sun = new THREE.DirectionalLight(0xfff1dc, 1.7);
        sun.castShadow = true; sun.shadow.mapSize.set(2048, 2048); sun.shadow.bias = -0.0008; sun.shadow.normalBias = 0.05;
        scene.add(sun, sun.target);

        // ── Helpers ──
        const mats = {};
        const M = (hex, extra = {}) => { const k = hex + JSON.stringify(extra); return mats[k] || (mats[k] = new THREE.MeshStandardMaterial({ color: hex, roughness: 0.78, metalness: 0.04, ...extra })); };
        const GLASS = new THREE.MeshStandardMaterial({ color: 0xcfe8ff, transparent: true, opacity: 0.22, roughness: 0.05, metalness: 0.1, depthWrite: false });
        // Sahig na may tile (texture, hindi magkakapatong na box → walang kislap / z-fighting)
        const floorCache = {};
        function floorMat(hex, w, d, tile = 1, line = 'rgba(255,255,255,0.45)', rough = 0.95) {
            const key = [hex, w.toFixed(2), d.toFixed(2), tile, line].join('|');
            if (floorCache[key]) return floorCache[key];
            const cv = document.createElement('canvas'); cv.width = cv.height = 64;
            const ctx = cv.getContext('2d');
            ctx.fillStyle = '#' + hex.toString(16).padStart(6, '0'); ctx.fillRect(0, 0, 64, 64);
            ctx.strokeStyle = line; ctx.lineWidth = 2; ctx.strokeRect(1, 1, 62, 62);
            const t = new THREE.CanvasTexture(cv);
            t.colorSpace = THREE.SRGBColorSpace; t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(w / tile, d / tile);
            t.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
            return (floorCache[key] = new THREE.MeshStandardMaterial({ map: t, roughness: rough, metalness: 0 }));
        }
        const box = (w, h, d, mat, x = 0, y = 0, z = 0, parent, shadow = true) => {
            const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
            m.position.set(x, y, z); m.castShadow = shadow; m.receiveShadow = true;
            if (parent) parent.add(m);
            return m;
        };
        const cyl = (rt, rb, h, mat, x, y, z, parent, seg = 14) => { const m = new THREE.Mesh(new THREE.CylinderGeometry(rt, rb, h, seg), mat); m.position.set(x, y, z); m.castShadow = true; m.receiveShadow = true; parent.add(m); return m; };
        const hexOf = c => parseInt(String(c).replace('#', ''), 16);
        const rnd = n => { let x = (n * 2654435761) >>> 0; return () => (x = (x * 1103515245 + 12345) >>> 0) / 4294967296; };
        const pick = a => a[Math.floor(Math.random() * a.length)];
        const SKIN = [0xf5d0a9, 0xe8b88a, 0xd39b6a, 0xb9784a, 0x8d5a3b];
        const HAIR = [0x1f2937, 0x3f2a1d, 0x5b3a29, 0x111827, 0x7c4a1e, 0xa16207];
        const PANTS = [0x1e3a8a, 0x374151, 0x1f2937, 0x44403c];
        const TALK = ['💬', '😄', '👍', '📐', '📋', '🤝', '💡', '✔️', '📞', '🙂'];

        // Pader na bumababa kapag nakaharap sa camera (parang Sims) — hawak sa ibaba
        let walls = [];
        function wall(w, h, d, mat, x, z, nx, nz, parent) {
            const g = new THREE.BoxGeometry(w, h, d); g.translate(0, h / 2, 0);
            const m = new THREE.Mesh(g, mat); m.position.set(x, 0, z); m.castShadow = true; m.receiveShadow = true;
            parent.add(m); walls.push({ m, n: new V3(nx, 0, nz), cur: 1 });
            return m;
        }

        // ── Tao ──
        function makePerson(p) {
            const room = VO.ROOM[p.room] || VO.ROOM.lobby, R = rnd(p.id);
            const skin = M(SKIN[Math.floor(R() * SKIN.length)]), hair = M(HAIR[Math.floor(R() * HAIR.length)]);
            const shirt = M(hexOf(room.accent)), pants = M(PANTS[Math.floor(R() * PANTS.length)]), shoe = M(0x111827);
            const long = R() < 0.4;
            const g = new THREE.Group(); g.scale.setScalar(1.12);
            const hips = new THREE.Group(); hips.position.y = 0.72; g.add(hips);
            const legs = [-0.14, 0.14].map(x => { const lg = new THREE.Group(); lg.position.x = x; hips.add(lg); box(0.24, 0.62, 0.26, pants, 0, -0.31, 0, lg); box(0.26, 0.12, 0.34, shoe, 0, -0.66, 0.04, lg); return lg; });
            box(0.62, 0.66, 0.36, shirt, 0, 1.06, 0, g);
            box(0.64, 0.1, 0.38, M(0x111827), 0, 0.75, 0, g);
            box(0.3, 0.12, 0.02, M(0xffffff), 0, 1.3, 0.19, g);                                   // kwelyo
            const arms = [-0.42, 0.42].map(x => { const a = new THREE.Group(); a.position.set(x, 1.34, 0); g.add(a); box(0.2, 0.58, 0.22, shirt, 0, -0.27, 0, a); box(0.2, 0.14, 0.22, skin, 0, -0.6, 0, a); return a; });
            const head = new THREE.Group(); head.position.y = 1.72; g.add(head);
            box(0.52, 0.52, 0.5, skin, 0, 0, 0, head);
            [-0.12, 0.12].forEach(x => box(0.07, 0.09, 0.02, M(0x111827), x, 0.02, 0.26, head));
            box(0.16, 0.04, 0.02, M(0x9a3412), 0, -0.13, 0.26, head);
            if (room.field) {
                const hat = new THREE.Mesh(new THREE.SphereGeometry(0.33, 16, 10, 0, Math.PI * 2, 0, Math.PI / 2), M(0xfacc15, { roughness: 0.4 }));
                hat.position.y = 0.22; hat.castShadow = true; head.add(hat);
                cyl(0.4, 0.4, 0.05, M(0xeab308, { roughness: 0.4 }), 0, 0.22, 0.04, head, 20);
            } else {
                box(0.56, 0.16, 0.54, hair, 0, 0.3, -0.01, head);
                box(0.56, long ? 0.5 : 0.22, 0.1, hair, 0, long ? 0.02 : 0.15, -0.25, head);
                if (long) [-0.28, 0.28].forEach(x => box(0.06, 0.4, 0.4, hair, x, 0.05, -0.05, head));
            }
            const tagEl = document.createElement('div');
            tagEl.className = 'vo-tag' + (p.me ? ' me' : '');
            tagEl.innerHTML = `<i></i>${VO.esc(p.me ? 'You' : p.name.split(' ')[0])}`;
            const tag = new CSS2DObject(tagEl); tag.position.set(0, 2.35, 0); g.add(tag);
            const bubEl = document.createElement('div'); bubEl.className = 'vo-bubble'; bubEl.style.opacity = '0';
            const bub = new CSS2DObject(bubEl); bub.position.set(0, 2.35, 0); g.add(bub);
            g.traverse(o => { if (o.isMesh) o.userData.pid = p.id; });
            return { g, legs, arms, head, tagEl, bubEl };
        }
        function pose(a, t) {
            const [ll, rl] = a.legs, [la, ra] = a.arms, s = a.state;
            if (s === 'sit') {
                a.g.position.y = -0.24;
                ll.rotation.x = rl.rotation.x = -1.45;
                const k = Math.sin(t * 14) * 0.08;
                la.rotation.x = -1.15 + k; ra.rotation.x = -1.15 - k;
                a.head.rotation.x = 0.12 + Math.sin(t * 1.3) * 0.04; a.head.rotation.y = 0;
            } else if (s === 'walk') {
                a.g.position.y = Math.abs(Math.sin(t * 9)) * 0.06;
                const w = Math.sin(t * 9) * 0.6;
                ll.rotation.x = w; rl.rotation.x = -w; la.rotation.x = -w * 0.8; ra.rotation.x = w * 0.8;
                a.head.rotation.x = 0; a.head.rotation.y = 0;
            } else {   // stand / talk / idle
                a.g.position.y = 0;
                ll.rotation.x = rl.rotation.x = 0;
                if (s === 'talk') { la.rotation.x = -0.5 + Math.sin(t * 5) * 0.35; ra.rotation.x = Math.sin(t * 2) * 0.08; a.head.rotation.y = Math.sin(t * 2.2) * 0.15; }
                else { la.rotation.x = ra.rotation.x = Math.sin(t * 2) * 0.05; a.head.rotation.y = 0; }
                a.head.rotation.x = Math.sin(t * 1.5) * 0.05;
            }
        }

        // ── Kasangkapan ──
        function officeChair(parent, x, z, rotY = 0) {
            const c = new THREE.Group(); c.position.set(x, 0, z); c.rotation.y = rotY; parent.add(c);
            const fab = M(0x1f2937), metal = M(0x6b7280, { metalness: 0.5, roughness: 0.35 });
            box(0.66, 0.12, 0.62, fab, 0, 0.56, 0, c);
            box(0.62, 0.72, 0.1, fab, 0, 1.0, -0.3, c);
            cyl(0.04, 0.04, 0.45, metal, 0, 0.3, 0, c, 8);
            [0, 1, 2, 3, 4].forEach(i => { const a = i / 5 * Math.PI * 2; box(0.34, 0.05, 0.06, metal, Math.cos(a) * 0.17, 0.06, Math.sin(a) * 0.17, c).rotation.y = -a; });
            return c;
        }
        function makeDesk(parent, x, z, online, accent) {
            const g = new THREE.Group(); g.position.set(x, 0, z); parent.add(g);
            const top = M(0xf5f5f4, { roughness: 0.5 }), leg = M(0x374151, { metalness: 0.4, roughness: 0.4 });
            box(2.0, 0.1, 1.05, top, 0, 1.0, 0, g);
            [-0.94, 0.94].forEach(xx => box(0.08, 0.95, 0.9, leg, xx, 0.48, 0, g));
            box(2.0, 0.55, 0.05, M(hexOf(accent)), 0, 1.33, 0.5, g);                                 // divider (kulay ng dept)
            box(0.16, 0.24, 0.12, leg, 0, 1.17, 0.22, g);
            const scr = box(1.0, 0.6, 0.07, online ? M(0x1e293b, { emissive: 0x0ea5e9, emissiveIntensity: 0.35 }) : M(0x111827), 0, 1.6, 0.24, g);
            const glow = new THREE.Mesh(new THREE.PlaneGeometry(0.88, 0.48), online ? M(0x38bdf8, { emissive: 0x0ea5e9, emissiveIntensity: 1.1, roughness: 0.3 }) : M(0x0f172a));
            glow.position.set(0, 1.6, 0.19); glow.rotation.y = Math.PI; g.add(glow);
            box(0.62, 0.04, 0.22, M(0xe5e7eb), -0.05, 1.07, -0.18, g);
            box(0.14, 0.18, 0.14, M(0xffffff), 0.75, 1.14, -0.15, g);                                // tasa
            if ((x * 7 + z * 3) % 2 > 1) box(0.4, 0.06, 0.3, M(0xfde68a), -0.7, 1.08, 0.05, g);     // papel
            officeChair(g, 0, -0.95);
            return g;
        }
        function plant(parent, x, z, s = 1) {
            cyl(0.26 * s, 0.2 * s, 0.5 * s, M(0x78350f), x, 0.25 * s, z, parent);
            const l = new THREE.Mesh(new THREE.IcosahedronGeometry(0.5 * s, 0), M(0x16a34a, { flatShading: true })); l.position.set(x, 0.95 * s, z); l.castShadow = true; parent.add(l);
            const l2 = new THREE.Mesh(new THREE.IcosahedronGeometry(0.34 * s, 0), M(0x22c55e, { flatShading: true })); l2.position.set(x + 0.15 * s, 1.35 * s, z - 0.05); l2.castShadow = true; parent.add(l2);
        }
        function cabinet(parent, x, z, rot = 0) {
            const g = new THREE.Group(); g.position.set(x, 0, z); g.rotation.y = rot; parent.add(g);
            box(0.8, 1.3, 0.6, M(0x9ca3af, { metalness: 0.3, roughness: 0.5 }), 0, 0.65, 0, g);
            [0.35, 0.75, 1.15].forEach(y => box(0.3, 0.04, 0.02, M(0x374151), 0, y, 0.31, g));
        }
        function printer(parent, x, z) {
            box(0.9, 0.7, 0.6, M(0x6b7280), x, 0.35, z, parent);
            box(0.8, 0.35, 0.55, M(0xe5e7eb), x, 0.88, z, parent);
            box(0.5, 0.03, 0.3, M(0xffffff), x, 1.07, z + 0.05, parent);
        }
        function cooler(parent, x, z) {
            box(0.55, 1.1, 0.55, M(0xe5e7eb), x, 0.55, z, parent);
            const b = cyl(0.22, 0.22, 0.5, M(0x7dd3fc, { transparent: true, opacity: 0.85, roughness: 0.1 }), x, 1.36, z, parent);
            b.castShadow = false;
        }
        function cone(parent, x, z) {
            const c = new THREE.Mesh(new THREE.ConeGeometry(0.22, 0.6, 12), M(0xf97316)); c.position.set(x, 0.34, z); c.castShadow = true; parent.add(c);
            box(0.5, 0.05, 0.5, M(0xc2410c), x, 0.03, z, parent);
        }
        function boardOn(wallMesh, x, w) {   // whiteboard na nakakabit sa pader (bumababa kasama ng pader)
            const b = box(w, 1.0, 0.05, M(0xffffff, { roughness: 0.3 }), x, 1.75, 0.14, wallMesh, false);
            box(w * 0.6, 0.05, 0.02, M(0xef4444), -w * 0.1, 0.18, 0.03, b, false);
            box(w * 0.45, 0.05, 0.02, M(0x3b82f6), -w * 0.15, 0, 0.03, b, false);
            box(w * 0.3, 0.05, 0.02, M(0x16a34a), -w * 0.2, -0.18, 0.03, b, false);
        }
        function windowOn(wallMesh, x, w, sideways = false) {
            const m = box(sideways ? 0.26 : w, 1.1, sideways ? w : 0.26, M(0x93c5fd, { emissive: 0x0c4a6e, emissiveIntensity: 0.3, roughness: 0.1 }), sideways ? 0 : x, 1.8, sideways ? x : 0, wallMesh, false);
            return m;
        }
        function clockOn(wallMesh, x) {
            const c = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, 0.05, 20), M(0xffffff)); c.rotation.x = Math.PI / 2; c.position.set(x, 2.45, 0.14); wallMesh.add(c);
            box(0.03, 0.18, 0.02, M(0x111827), x, 2.5, 0.17, wallMesh, false);
        }
        function textTexture(text, color = '#ffffff', bg = null, w = 512, h = 128, font = '800 72px system-ui, sans-serif') {
            const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
            const ctx = cv.getContext('2d');
            if (bg) { ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h); }
            ctx.fillStyle = color; ctx.font = font; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(text, w / 2, h / 2 + 4);
            const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace; return t;
        }
        function tree(parent, x, z, s = 1) {
            cyl(0.15 * s, 0.2 * s, 1.2 * s, M(0x78350f), x, 0.6 * s, z, parent, 8);
            const l = new THREE.Mesh(new THREE.IcosahedronGeometry(1.0 * s, 0), M(0x15803d, { flatShading: true })); l.position.set(x, 1.9 * s, z); l.castShadow = true; parent.add(l);
            const l2 = new THREE.Mesh(new THREE.IcosahedronGeometry(0.7 * s, 0), M(0x22c55e, { flatShading: true })); l2.position.set(x + 0.3 * s, 2.5 * s, z + 0.2); l2.castShadow = true; parent.add(l2);
        }

        // ── Sukat ──
        const DX = 3.0, DZ = 3.4, CW = 3.4, H = 2.9, LW = 9;
        let signs = [], world = null, bldg = null, rooms = {}, avatars = new Map(), pickables = [], offTags = [], doors = [], bounds = new THREE.Box3();
        let corrs = [0], entrance = { out: new V3(), inn: new V3() }, prevOnline = null;
        const WALKX = -1.0;   // daanan sa loob ng lobby na nagdudugtong sa mga hallway
        const L = (r, x, z) => { const v = new V3(x, 0, z); return r.g.localToWorld(v); };

        // Room "spec" (sukat bago ilagay)
        function deptSpec(k, ppl, all) {
            const cols = Math.max(2, Math.min(4, Math.ceil(Math.sqrt(Math.max(1, ppl.length) * 1.4))));
            const rows = Math.max(1, Math.ceil(ppl.length / cols));
            return { k, kind: 'dept', ppl, all, cols, rows, W: cols * DX + 2.6, D: rows * DZ + 1.6 };
        }
        function disposeWorld() {
            if (!world) return;
            world.traverse(o => { if (o.geometry) o.geometry.dispose(); if (o.isCSS2DObject && o.element) o.element.remove(); });
            scene.remove(world); world = null;
        }

        function build() {
            disposeWorld();
            world = new THREE.Group(); scene.add(world);
            bldg = new THREE.Group(); world.add(bldg);
            rooms = {}; avatars = new Map(); pickables = []; offTags = []; doors = []; walls = []; signs = []; loopState.far = undefined;
            const list = VO.visible(), showOff = VO.showOff();
            const byRoom = {};
            list.forEach(p => (byRoom[p.room] = byRoom[p.room] || []).push(p));
            Object.values(byRoom).forEach(a => a.sort((x, y) => (y.online - x.online) || x.name.localeCompare(y.name)));
            const deptKeys = VO.ROOMS.map(r => r[0]).filter(k => k !== 'lobby' && byRoom[k]);
            const specs = deptKeys.map(k => deptSpec(k, showOff ? byRoom[k] : byRoom[k].filter(p => p.online), byRoom[k]));
            specs.push({ k: 'pantry', kind: 'pantry', ppl: [], all: [], W: 7.2, D: 0 });
            specs.push({ k: 'meeting', kind: 'meeting', ppl: [], all: [], W: 8.4, D: 0 });
            // hatiin sa mga wing (bawat wing: hallway sa gitna, kuwarto sa magkabilang gilid) para halos parisukat ang gusali
            const totalW = specs.reduce((t, x) => t + x.W, 0);
            const nB = Math.max(1, Math.min(4, Math.round(Math.sqrt(totalW / 34))));
            const blocks = Array.from({ length: nB }, () => ({ north: [], south: [], wn: 0, ws: 0 }));
            specs.forEach(sp => {
                const bk = blocks.reduce((m, x) => (x.wn + x.ws < m.wn + m.ws ? x : m), blocks[0]);
                if (bk.wn <= bk.ws) { bk.north.push(sp); bk.wn += sp.W; } else { bk.south.push(sp); bk.ws += sp.W; }
            });
            let zCur = 0, maxLen = 10, zmin = Infinity, zmax = -Infinity;
            const GAPZ = 1.2;
            blocks.forEach((bk, bi) => {
                bk.len = Math.max(bk.wn, bk.ws, 10);
                const fill = (row, w) => { if (row.length) row[row.length - 1].W += bk.len - w; };
                fill(bk.north, bk.wn); fill(bk.south, bk.ws);
                bk.Dn = Math.max(6.6, ...bk.north.map(x => x.D)); bk.Ds = Math.max(6.6, ...bk.south.map(x => x.D));
                if (bi > 0) zCur += GAPZ + bk.Dn + CW / 2;
                bk.cz = zCur;
                zmin = Math.min(zmin, bk.cz - CW / 2 - bk.Dn); zmax = Math.max(zmax, bk.cz + CW / 2 + bk.Ds);
                zCur = bk.cz + CW / 2 + bk.Ds;
                maxLen = Math.max(maxLen, bk.len);
            });
            corrs = blocks.map(bk => bk.cz);

            // ── lupa, sahig ng gusali ──
            const ground = box(maxLen + LW + 44, 0.2, (zmax - zmin) + 36, M(0x86c98a, { roughness: 1 }), (maxLen - LW) / 2, -0.25, (zmin + zmax) / 2, world, false);
            const zEnt = (zmin + zmax) / 2;
            box(11, 0.05, 2.6, M(0xd6d3d1), -LW - 5.5, -0.13, zEnt, world, false);
            [[-LW - 6, zEnt - 4.5], [-LW - 6, zEnt + 4.5], [-LW - 2.5, zmin - 3], [maxLen + 3, zmin - 2.5], [maxLen + 3, zmax + 2.5], [maxLen / 2, zmax + 3.2], [maxLen / 3, zmin - 3.2], [-LW - 2.5, zmax + 3], [maxLen + 3.5, (zmin + zmax) / 2]].forEach(([x, z], i) => tree(world, x, z, 0.9 + (i % 3) * 0.15));
            box(maxLen + LW + 0.6, 0.22, zmax - zmin + 0.6, M(0x64748b), (maxLen - LW) / 2, -0.12, (zmin + zmax) / 2, bldg, false);

            // ── bawat wing ──
            blocks.forEach((bk, bi) => {
                box(bk.len, 0.1, CW, floorMat(0xe7e5e4, bk.len, CW, 1, 'rgba(120,113,108,0.35)', 0.6), bk.len / 2, 0.03, bk.cz, bldg, false);
                box(bk.len, 0.02, 1.1, M(0x57534e, { roughness: 0.95 }), bk.len / 2, 0.09, bk.cz, bldg, false);
                for (let x = 0.6; x < bk.len; x += 1.2) { box(0.6, 0.015, 0.12, M(0xf59e0b), x, 0.1, bk.cz + CW / 2 - 0.12, bldg, false); box(0.6, 0.015, 0.12, M(0xf59e0b), x, 0.1, bk.cz - CW / 2 + 0.12, bldg, false); }
                const endW = wall(0.22, H, CW, M(0xf5f5f4), bk.len + 0.11, bk.cz, 1, 0, bldg); windowOn(endW, 0, 2.2, true);
                const hs = document.createElement('div'); hs.className = 'vo-tag'; hs.style.background = 'rgba(55,65,81,.85)'; hs.textContent = 'Hallway ' + String.fromCharCode(65 + bi);
                const ho = new CSS2DObject(hs); ho.position.set(1.6, 0.4, bk.cz); bldg.add(ho);
                // hardin sa pagitan ng mga wing
                if (bi > 0) {
                    const gz = bk.cz - CW / 2 - bk.Dn - GAPZ / 2;
                    box(maxLen, 0.15, GAPZ, M(0x4ade80, { roughness: 1 }), maxLen / 2, 0.02, gz, bldg, false);
                    for (let x = 1.5; x < maxLen - 0.5; x += 4) plant(bldg, x, gz, 0.8);
                    wall(0.2, H, GAPZ, M(0xf5f5f4), 0, gz, 1, 0, bldg);
                }
                const place = (row, side) => {
                    let x = 0;
                    row.forEach((sp, i) => {
                        const D = side === 'n' ? bk.Dn : bk.Ds;
                        const g = new THREE.Group();
                        g.position.set(x + sp.W / 2, 0, side === 'n' ? bk.cz - CW / 2 - D / 2 : bk.cz + CW / 2 + D / 2);
                        g.rotation.y = side === 'n' ? 0 : Math.PI;
                        bldg.add(g); g.updateMatrixWorld(true);
                        const r = { ...sp, D, g, side, rot: g.rotation.y, doorX: -sp.W / 2 + 1.25, frontZ: D / 2 - 0.95, first: i === 0, desks: [], corrZ: bk.cz };
                        rooms[sp.k] = r;
                        buildRoom(r);
                        x += sp.W;
                    });
                };
                place(bk.north, 'n'); place(bk.south, 's');
            });
            buildLobby(byRoom.lobby || [], showOff, zmin, zmax);
            bldg.updateMatrixWorld(true);
            scene.updateMatrixWorld(true);

            // mga tao
            const first = prevOnline === null;
            const nowOn = new Set();
            Object.values(rooms).forEach(r => r.desks.forEach(d => {
                if (!d.p.online) return;
                nowOn.add(d.p.id);
                const av = makePerson(d.p);
                world.add(av.g);
                av.g.traverse(o => { if (o.isMesh) pickables.push(o); });
                const a = { ...av, p: d.p, room: r, seatL: d.seatL, aisleX: d.aisleX, seatW: L(r, d.seatL.x, d.seatL.z), state: 'sit', path: [], t0: Math.random() * 10, next: performance.now() + 4000 + Math.random() * 16000, busy: false };
                a.g.position.copy(a.seatW); a.g.rotation.y = r.rot;
                avatars.set(d.p.id, a);
                // bagong online → papasok mula sa entrance
                if (!first && !prevOnline.has(d.p.id)) {
                    a.g.position.copy(entrance.out); a.state = 'walk'; a.busy = true;
                    a.path = arrivalRoute(r, a.seatL, a.aisleX);
                    a.onArrive = () => sitDown(a);
                }
            }));
            prevOnline = nowOn;

            bounds.setFromObject(bldg);
            const size = Math.max(maxLen + LW, zmax - zmin) + 8;
            const c = bounds.getCenter(new V3());
            sun.position.set(c.x + size * 0.4, size * 0.95, c.z + size * 0.55); sun.target.position.copy(c);
            const sc = sun.shadow.camera; sc.left = -size; sc.right = size; sc.top = size; sc.bottom = -size; sc.near = 0.5; sc.far = size * 4; sc.updateProjectionMatrix();
            cutaway(true);
            applySearch();
            if (!build.done) { build.done = true; home(false); }
        }

        // Pader + harap na salamin + pinto (local: harap = +z, papunta sa hallway)
        function shell(r, floorHex, accentHex, signHtml) {
            const { g, W, D } = r;
            box(W, 0.1, D, floorMat(floorHex, W, D), 0, 0.04, 0, g, false);
            const wm = M(0xf5f5f4), trim = M(accentHex);
            const s = r.rot === 0 ? 1 : -1;   // para tama ang direksyon ng normal sa mundo
            const back = wall(W, H, 0.2, wm, 0, -D / 2, 0, -s, g);
            box(W, 0.16, 0.24, trim, 0, H - 0.05, 0, back, false);
            if (r.first) { const lw = wall(0.2, H, D, wm, -W / 2, 0, -s, 0, g); box(0.24, 0.16, D, trim, 0, H - 0.05, 0, lw, false); }
            const rw = wall(0.2, H, D, wm, W / 2, 0, s, 0, g); box(0.24, 0.16, D, trim, 0, H - 0.05, 0, rw, false);
            // harap: salamin + frame + pinto
            const fz = D / 2, dx = r.doorX, dw = 1.4;
            const frame = M(0x374151, { metalness: 0.4, roughness: 0.4 });
            const seg = (x0, x1) => {
                const w = x1 - x0; if (w <= 0.05) return;
                const cx = (x0 + x1) / 2;
                box(w, 0.35, 0.16, M(0xe7e5e4), cx, 0.175, fz, g);
                const gl = new THREE.Mesh(new THREE.BoxGeometry(w, H - 0.5, 0.04), GLASS); gl.position.set(cx, 0.35 + (H - 0.5) / 2, fz); g.add(gl);
                box(w, 0.1, 0.14, frame, cx, H - 0.1, fz, g, false);
                for (let x = x0; x <= x1 + 0.01; x += Math.max(1.6, w / Math.round(w / 2))) box(0.06, H, 0.12, frame, x, H / 2, fz, g, false);
            };
            seg(-W / 2, dx - dw / 2); seg(dx + dw / 2, W / 2);
            box(dw, 0.4, 0.14, frame, dx, H - 0.2, fz, g, false);
            const hinge = new THREE.Group(); hinge.position.set(dx - dw / 2, 0, fz); g.add(hinge);
            const panel = new THREE.Mesh(new THREE.BoxGeometry(dw - 0.06, H - 0.45, 0.05), GLASS); panel.position.set(dw / 2, (H - 0.45) / 2 + 0.02, 0); hinge.add(panel);
            box(0.06, H - 0.45, 0.07, frame, 0.04, (H - 0.45) / 2, 0, hinge, false);
            box(0.06, H - 0.45, 0.07, frame, dw - 0.1, (H - 0.45) / 2, 0, hinge, false);
            box(0.06, 0.3, 0.12, M(0xd1d5db, { metalness: 0.6 }), dw - 0.25, 1.05, 0, hinge, false);   // hawakan
            doors.push({ hinge, at: L(r, dx, fz), open: 0, kind: 'swing' });
            // karatula sa ibabaw ng pinto
            const sg = document.createElement('div');
            sg.className = 'vo-sign'; sg.style.background = '#' + accentHex.toString(16).padStart(6, '0'); sg.dataset.roomGo = r.k; sg.innerHTML = signHtml; signs.push(sg);
            const so = new CSS2DObject(sg); so.position.set(dx + 1.6, H + 0.35, fz); g.add(so);
            return { back, rw };
        }

        function buildRoom(r) {
            if (r.kind === 'pantry') return buildPantry(r);
            if (r.kind === 'meeting') return buildMeeting(r);
            const room = VO.ROOM[r.k], accent = hexOf(room.accent);
            const on = r.all.filter(p => p.online).length;
            const { back, rw } = shell(r, hexOf(room.floor), accent, `<i class="fa-solid ${room.icon}"></i><span class="nm">${VO.esc(room.name)}</span> <b>${on}/${r.all.length}</b>`);
            boardOn(back, -r.W / 2 + 2.2, Math.min(2.4, r.W / 3)); windowOn(back, r.W / 2 - 2, 1.8); clockOn(back, 0.6);
            windowOn(rw, -r.D / 4, 1.6, true);
            plant(r.g, r.W / 2 - 0.6, r.D / 2 - 0.7);
            cabinet(r.g, r.W / 2 - 0.55, -r.D / 2 + 0.6, -Math.PI / 2);
            if (r.ppl.length > 3) printer(r.g, r.W / 2 - 0.6, -r.D / 2 + 1.7);
            else cooler(r.g, r.W / 2 - 0.6, -r.D / 2 + 1.6);
            if (room.field) { cone(r.g, -r.W / 2 + 0.6, r.D / 2 - 0.5); cone(r.g, -r.W / 2 + 1.1, r.D / 2 - 0.3); }
            if (!r.ppl.length) {
                const em = document.createElement('div'); em.className = 'vo-tag'; em.style.background = 'rgba(107,114,128,.8)'; em.textContent = 'Nobody online here';
                const eo = new CSS2DObject(em); eo.position.set(0, 0.6, 0); r.g.add(eo);
            }
            const ox = -((r.cols - 1) * DX) / 2 + 0.2;
            r.ppl.forEach((p, i) => {
                const c = i % r.cols, row = Math.floor(i / r.cols);
                const x = ox + c * DX, z = -r.D / 2 + 1.75 + row * DZ;
                const d = makeDesk(r.g, x, z, p.online, room.accent);
                d.traverse(o => { if (o.isMesh) { o.userData.pid = p.id; pickables.push(o); } });
                r.desks.push({ p, seatL: { x, z: z - 0.95 }, aisleX: x - DX / 2 });
                if (!p.online) {
                    const pl = document.createElement('div'); pl.className = 'vo-tag far'; pl.style.background = 'rgba(107,114,128,.85)'; pl.textContent = p.name.split(' ')[0];
                    const po = new CSS2DObject(pl); po.position.set(0, 1.3, -0.95); d.add(po); offTags.push(pl);
                }
            });
        }

        function buildPantry(r) {
            const { g, W, D } = r;
            const { back } = shell(r, 0xfef3c7, 0xb45309, `<i class="fa-solid fa-mug-hot"></i><span class="nm">Pantry</span>`);
            windowOn(back, W / 2 - 1.6, 1.8); clockOn(back, -W / 2 + 1.5);
            const bz = -D / 2 + 0.45;
            box(4.4, 1.0, 0.8, M(0xf5f5f4), 0.6, 0.5, bz, g);                     // counter
            box(4.5, 0.08, 0.85, M(0x44403c, { roughness: 0.4 }), 0.6, 1.04, bz, g);
            box(0.6, 0.7, 0.5, M(0x111827, { metalness: 0.3 }), 1.6, 1.42, bz - 0.05, g);   // coffee machine
            box(0.3, 0.12, 0.06, M(0xef4444, { emissive: 0xef4444, emissiveIntensity: 0.6 }), 1.6, 1.6, bz + 0.21, g, false);
            box(0.5, 0.3, 0.4, M(0xe5e7eb), -0.6, 1.23, bz, g);                     // microwave
            box(1.0, 2.1, 0.8, M(0xe5e7eb, { metalness: 0.2 }), W / 2 - 0.65, 1.05, bz + 0.05, g);   // ref
            const tbl = new V3(-0.4, 0, 0.7);
            cyl(0.75, 0.75, 0.08, M(0xf5f5f4), tbl.x, 1.0, tbl.z, g, 24); cyl(0.08, 0.08, 0.95, M(0x374151), tbl.x, 0.5, tbl.z, g, 8);
            [[-1.15, 0.7], [0.35, 0.7], [-0.4, 1.5]].forEach(([x, z]) => { cyl(0.25, 0.25, 0.08, M(0xb45309), x, 0.62, z, g, 14); cyl(0.04, 0.04, 0.6, M(0x374151), x, 0.3, z, g, 6); });
            plant(g, -W / 2 + 0.6, -D / 2 + 0.7);
            r.spots = [
                { x: 1.6, z: bz + 0.95, face: Math.PI, say: ['☕', '☕', '🍪'] },
                { x: 0.9, z: bz + 0.95, face: Math.PI, say: ['🥤', '🍞'] },
                { x: -1.15, z: 0.7, face: Math.PI / 2, say: ['☕', '😄', '💬'] },
                { x: 0.35, z: 0.7, face: -Math.PI / 2, say: ['☕', '💬', '🍩'] },
            ];
        }

        function buildMeeting(r) {
            const { g, W, D } = r;
            const { back } = shell(r, 0xe0e7ff, 0x4338ca, `<i class="fa-solid fa-people-group"></i><span class="nm">Meeting Room</span>`);
            const tv = box(2.6, 1.4, 0.08, M(0x111827), 0.3, 1.7, 0.15, back, false);
            box(2.4, 1.2, 0.02, M(0x1d4ed8, { emissive: 0x1d4ed8, emissiveIntensity: 0.5 }), 0, 0, 0.05, tv, false);
            const tz = -0.4;
            box(4.2, 0.12, 1.6, M(0x78350f, { roughness: 0.5 }), 0.3, 1.0, tz, g);
            [-1.6, 2.2].forEach(x => box(0.15, 0.95, 1.2, M(0x451a03), x, 0.48, tz, g));
            const spots = [];
            [-1.1, 0.3, 1.7].forEach(x => { officeChair(g, x, tz + 1.25, Math.PI); spots.push({ x, z: tz + 1.15, face: Math.PI }); });
            [-1.1, 0.3, 1.7].forEach(x => officeChair(g, x, tz - 1.25, 0));
            spots.push({ x: -2.35, z: tz, face: Math.PI / 2 }, { x: 2.95, z: tz, face: -Math.PI / 2 });
            plant(g, W / 2 - 0.6, -D / 2 + 0.6); plant(g, -W / 2 + 0.6, -D / 2 + 0.6);
            r.spots = spots.map(s => ({ ...s, say: ['💬', '📊', '📋', '💡', '🤝'] }));
        }

        function buildLobby(ppl, showOff, zmin, zmax) {
            const g = new THREE.Group(); g.position.set(-LW / 2, 0, 0); bldg.add(g); g.updateMatrixWorld(true);
            const D = zmax - zmin, zc = (zmin + zmax) / 2;
            const r = { k: 'lobby', kind: 'lobby', g, W: LW, D, rot: 0, desks: [], all: ppl, ppl: showOff ? ppl : ppl.filter(p => p.online) };
            rooms.lobby = r;
            box(LW, 0.1, D, floorMat(0xf8fafc, LW, D, 1.5, 'rgba(148,163,184,0.55)', 0.35), 0, 0.04, zc, g, false);   // marble
            box(4.4, 0.02, 3.4, M(0xb45309, { roughness: 1 }), -1.4, 0.1, (zmin + zmax) / 2, g, false);       // rug
            const wm = M(0xf5f5f4), trim = M(0xb45309);
            const nw = wall(LW, H + 0.6, 0.2, wm, 0, zmin, 0, -1, g); box(LW, 0.18, 0.24, trim, 0, H + 0.54, 0, nw, false);
            const sw = wall(LW, H + 0.6, 0.2, wm, 0, zmax, 0, 1, g); box(LW, 0.18, 0.24, trim, 0, H + 0.54, 0, sw, false);
            // logo sa pader
            const logo = new THREE.Mesh(new THREE.PlaneGeometry(5.2, 1.3), new THREE.MeshStandardMaterial({ map: textTexture('NOBLEHOME', '#ffffff', '#1f2937'), roughness: 0.6 }));
            logo.position.set(0, 2.1, 0.12); nw.add(logo);
            box(5.2, 0.12, 0.06, M(0xf59e0b), 0, 1.38, 0.13, nw, false);
            // kanlurang pader na may sliding door
            const west = -LW / 2, gap = 1.5, ze = (zmin + zmax) / 2;
            [[zmin, ze - gap], [ze + gap, zmax]].forEach(([a, b]) => { const w = wall(0.2, H + 0.6, b - a, wm, west, (a + b) / 2, -1, 0, g); windowOn(w, 0, Math.min(2.4, (b - a) - 1.2), true); });
            const frame = M(0x374151, { metalness: 0.4, roughness: 0.4 });
            box(0.16, 0.45, gap * 2, frame, west, H + 0.35, ze, g, false);
            const slide = [-1, 1].map(sd => {
                const pnl = new THREE.Group(); pnl.position.set(west, 0, ze + sd * gap / 2); g.add(pnl);
                const gl = new THREE.Mesh(new THREE.BoxGeometry(0.05, H, gap - 0.04), GLASS); gl.position.y = H / 2; pnl.add(gl);
                box(0.07, H, 0.06, frame, 0, H / 2, -sd * (gap / 2 - 0.03), pnl, false);
                return { pnl, sd };
            });
            doors.push({ slide, at: L(r, west, ze), open: 0, kind: 'slide', gap, z0: ze });
            entrance.out = L(r, west - 2.2, ze); entrance.inn = L(r, west + 1.2, ze);
            box(1.6, 0.02, 2.4, M(0x44403c, { roughness: 1 }), west + 0.9, 0.1, ze, g, false);
            // reception
            box(0.9, 1.1, 3.0, M(0xf5f5f4), LW / 2 - 2.4, 0.55, zmin + 2.2, g);
            box(1.0, 0.08, 3.1, M(0xb45309, { roughness: 0.4 }), LW / 2 - 2.4, 1.14, zmin + 2.2, g);
            box(0.05, 0.25, 2.0, M(0xf59e0b), LW / 2 - 1.93, 0.75, zmin + 2.2, g, false);
            // sofa + mesa
            const sofa = (x, z, rot) => {
                const s = new THREE.Group(); s.position.set(x, 0, z); s.rotation.y = rot; g.add(s);
                box(2.4, 0.45, 0.9, M(0x0f766e), 0, 0.32, 0, s); box(2.4, 0.7, 0.25, M(0x0f766e), 0, 0.7, -0.35, s);[-1.15, 1.15].forEach(xx => box(0.2, 0.6, 0.9, M(0x115e59), xx, 0.45, 0, s));
            };
            sofa(-1.2, zmax - 1.0, Math.PI); sofa(-LW / 2 + 1.0, zmax - 3.2, Math.PI / 2);
            box(1.4, 0.4, 0.8, M(0x78350f), -1.2, 0.3, zmax - 2.4, g);
            plant(g, LW / 2 - 0.7, zmax - 0.8, 1.2); plant(g, -LW / 2 + 0.7, zmin + 0.7, 1.2); plant(g, -LW / 2 + 0.7, ze - gap - 0.6); plant(g, -LW / 2 + 0.7, ze + gap + 0.6);
            const sg = document.createElement('div'); sg.className = 'vo-sign'; sg.style.background = '#6b7280'; sg.dataset.roomGo = 'lobby';
            sg.innerHTML = `<i class="fa-solid fa-door-open"></i>Lobby · Reception${ppl.length ? ` <b>${ppl.filter(p => p.online).length}/${ppl.length}</b>` : ''}`;
            const so = new CSS2DObject(sg); so.position.set(0, H + 1.2, zmin); g.add(so);
            // mga mesa ng "lobby" (walang department)
            r.ppl.slice(0, 6).forEach((p, i) => {
                const x = -LW / 2 + 1.6 + (i % 2) * DX, z = zmin + 1.9 + Math.floor(i / 2) * DZ;
                const d = makeDesk(g, x, z, p.online, '#6b7280');
                d.traverse(o => { if (o.isMesh) { o.userData.pid = p.id; pickables.push(o); } });
                r.desks.push({ p, seatL: { x, z: z - 0.95 }, aisleX: x + 1.3 });
            });
        }

        // ── Mga daan ──  (kuwarto → pinto → hallway → [lobby kung ibang wing] → hallway → pinto → kuwarto)
        const jit = () => (Math.random() - 0.5) * 1.2;
        function pathOut(r, fromL, aisle) {
            const a1 = aisle ?? fromL.x;
            if (r.kind === 'lobby') { const p = L(r, a1, fromL.z); return [L(r, fromL.x, fromL.z), p, new V3(WALKX, 0, p.z)]; }
            return [L(r, a1, fromL.z), L(r, a1, r.frontZ), L(r, r.doorX, r.frontZ), L(r, r.doorX, r.D / 2 - 0.2), L(r, r.doorX, r.D / 2 + 1.1)];
        }
        function pathIn(r, toL, aisle) { const p = pathOut(r, toL, aisle).reverse(); p.push(L(r, toL.x, toL.z)); return p; }
        function route(fromR, fromL, fromA, toR, toL, toA) {
            if (fromR === toR && fromR.kind !== 'lobby') {
                const a1 = fromA ?? fromL.x, a2 = toA ?? toL.x;
                return [L(fromR, a1, fromL.z), L(fromR, a1, fromR.frontZ), L(fromR, a2, fromR.frontZ), L(fromR, a2, toL.z), L(fromR, toL.x, toL.z)];
            }
            const out = pathOut(fromR, fromL, fromA), inn = pathIn(toR, toL, toA);
            const A = out[out.length - 1], B = inn[0];
            const zA = fromR.kind === 'lobby' ? A.z : fromR.corrZ + jit(), zB = toR.kind === 'lobby' ? B.z : toR.corrZ + jit();
            const pts = [...out];
            if (fromR.kind !== 'lobby') pts.push(new V3(A.x, 0, zA));
            if (fromR.kind === 'lobby' || toR.kind === 'lobby' || fromR.corrZ !== toR.corrZ) pts.push(new V3(WALKX, 0, zA), new V3(WALKX, 0, zB));
            if (toR.kind !== 'lobby') pts.push(new V3(B.x, 0, zB));
            return [...pts, ...inn];
        }
        function arrivalRoute(r, toL, toA) {
            const lob = rooms.lobby, l = lob.g.worldToLocal(entrance.inn.clone());
            return [entrance.inn.clone(), ...route(lob, { x: l.x, z: l.z }, null, r, toL, toA)];
        }

        // ── Mga gawain (pakikipag-usap, kape, meeting) ──
        function say(a, emoji, ms = 1600) {
            a.bubEl.textContent = emoji; a.bubEl.style.opacity = '1';
            clearTimeout(a.sayT); a.sayT = setTimeout(() => { a.bubEl.style.opacity = '0'; }, ms);
        }
        function sitDown(a) {
            a.state = 'sit'; a.busy = false; a.g.position.copy(a.seatW); a.g.rotation.y = a.room.rot;
            a.next = performance.now() + 10000 + Math.random() * 25000;
        }
        function goHome(a, fromR, fromL, fromA) {
            a.state = 'walk'; a.path = route(fromR, fromL, fromA, a.room, a.seatL, a.aisleX);
            a.onArrive = () => sitDown(a);
        }
        function faceTo(a, b) { a.g.rotation.y = Math.atan2(b.g.position.x - a.g.position.x, b.g.position.z - a.g.position.z); }
        function chatWith(a, b) {   // si a ay pupunta kay b
            a.busy = b.busy = true;
            const side = b.aisleX < b.seatL.x ? 0.45 : -0.45;
            const spot = { x: b.aisleX + side, z: b.seatL.z + 0.15 };
            a.state = 'walk'; a.path = route(a.room, a.seatL, a.aisleX, b.room, spot, b.aisleX);
            a.onArrive = () => {
                if (!avatars.has(b.p.id)) return goHome(a, b.room, spot, b.aisleX);
                b.state = 'stand'; b.g.position.copy(b.seatW); faceTo(b, a); faceTo(a, b);
                a.state = 'talk';
                const turns = 3 + Math.floor(Math.random() * 3);
                let n = 0;
                const tick = () => {
                    if (!a.g.parent) return;
                    const speaker = n % 2 ? b : a; speaker.state = 'talk'; (n % 2 ? a : b).state = 'stand';
                    say(speaker, pick(TALK), 1300);
                    if (++n < turns * 2) a.talkT = setTimeout(tick, 1500);
                    else setTimeout(() => { if (!a.g.parent) return; sitDown(b); goHome(a, b.room, spot, b.aisleX); }, 1200);
                };
                tick();
            };
        }
        function visitSpot(a, r, spots, holdMs) {
            const free = spots.filter(s => !s.taken);
            if (!free.length) return false;
            const s = pick(free); s.taken = true; a.busy = true;
            a.state = 'walk'; a.path = route(a.room, a.seatL, a.aisleX, r, s, null);
            a.onArrive = () => {
                a.state = 'idle'; a.g.rotation.y = r.rot + s.face;
                let n = 0;
                const tick = () => { if (!a.g.parent) return; say(a, pick(s.say), 1400); a.state = r.kind === 'meeting' && n % 2 ? 'talk' : 'idle'; if (++n * 1900 < holdMs) a.talkT = setTimeout(tick, 1900); else { s.taken = false; goHome(a, r, s, null); } };
                tick();
            };
            return true;
        }
        function schedule(now) {
            const all = [...avatars.values()];
            const active = all.filter(a => a.busy).length;
            const cap = Math.max(1, Math.min(6, Math.ceil(all.length / 3)));
            if (active >= cap) return;
            const a = all.find(x => !x.busy && x.state === 'sit' && now > x.next);
            if (!a) return;
            a.next = now + 8000 + Math.random() * 20000;
            const free = all.filter(x => x !== a && !x.busy && x.state === 'sit');
            const mates = free.filter(x => x.room === a.room), others = free.filter(x => x.room !== a.room);
            const roll = Math.random();
            if (roll < 0.35 && mates.length) return chatWith(a, pick(mates));
            if (roll < 0.62 && others.length) return chatWith(a, pick(others));
            if (roll < 0.85 && rooms.pantry && visitSpot(a, rooms.pantry, rooms.pantry.spots, 4000 + Math.random() * 3000)) return;
            if (rooms.meeting && visitSpot(a, rooms.meeting, rooms.meeting.spots, 7000 + Math.random() * 5000)) return;
            if (mates.length) chatWith(a, pick(mates));
        }
        function step(a, dt) {
            if (a.state !== 'walk') return;
            const tgt = a.path[0];
            if (!tgt) { a.state = 'idle'; const f = a.onArrive; a.onArrive = null; f && f(); return; }
            const pos = a.g.position, dx = tgt.x - pos.x, dz = tgt.z - pos.z, len = Math.hypot(dx, dz);
            const sp = 1.9 * dt;
            if (len <= sp) { pos.x = tgt.x; pos.z = tgt.z; a.path.shift(); return; }
            pos.x += dx / len * sp; pos.z += dz / len * sp;
            let diff = Math.atan2(dx, dz) - a.g.rotation.y; diff = Math.atan2(Math.sin(diff), Math.cos(diff));
            a.g.rotation.y += diff * Math.min(1, dt * 10);
        }

        // ── Pinto at pader ──
        function doorsTick(dt) {
            doors.forEach(d => {
                let near = false;
                avatars.forEach(a => { if (!near && a.state === 'walk') { const p = a.g.position; if (Math.hypot(p.x - d.at.x, p.z - d.at.z) < 1.9) near = true; } });
                d.open += ((near ? 1 : 0) - d.open) * Math.min(1, dt * 6);
                if (d.kind === 'swing') d.hinge.rotation.y = d.open * 1.45;
                else d.slide.forEach(s => s.pnl.position.z = d.z0 + s.sd * (d.gap / 2 + d.open * (d.gap - 0.1)));
            });
        }
        const camDir = new V3();
        function cutaway(snap) {
            camDir.subVectors(cam.position, controls.target); camDir.y = 0; camDir.normalize();
            walls.forEach(w => {
                const low = w.n.dot(camDir) > 0.2;          // nakaharap sa camera → ibaba
                const want = low ? 0.16 : 1;
                w.cur = snap ? want : w.cur + (want - w.cur) * 0.2;
                w.m.scale.y = w.cur;
            });
        }

        // ── Camera ──
        let az = 0.78;
        const camState = { from: null, to: null, t: 1 };
        function viewFor(target, span) {
            const w = stage.clientWidth || 800, h = stage.clientHeight || 500, aspect = w / h;
            const zoom = Math.min(4.5, Math.max(0.25, (aspect >= 1 ? 22 / span : 22 / (span / aspect)) * 0.95));
            const dist = 80;
            return { pos: new V3(target.x + Math.sin(az) * dist, target.y + dist * 0.95, target.z + Math.cos(az) * dist), target: target.clone(), zoom };
        }
        function fly(v, animate = true) {
            if (!animate) { cam.position.copy(v.pos); controls.target.copy(v.target); cam.zoom = v.zoom; cam.updateProjectionMatrix(); controls.update(); return; }
            camState.from = { pos: cam.position.clone(), target: controls.target.clone(), zoom: cam.zoom }; camState.to = v; camState.t = 0;
        }
        function home(animate = true) {
            const c = bounds.getCenter(new V3()), s = bounds.getSize(new V3());
            fly(viewFor(new V3(c.x, 0, c.z), Math.max(s.x, s.z) * 1.0), animate);
        }
        VO.focusRoom = key => {
            const r = rooms[key]; if (!r) return false;
            const c = r.kind === 'lobby' ? L(r, 0, 0) : L(r, 0, 0);
            fly(viewFor(c, Math.max(r.W, r.D) + 2)); return true;
        };
        VO.focusPerson = id => {
            const a = avatars.get(id);
            if (a) { fly(viewFor(a.g.position.clone(), 10)); highlight(id); return true; }
            let found = null;
            world && world.traverse(o => { if (!found && o.isMesh && o.userData.pid === id) found = o; });
            if (found) { const v = new V3(); found.getWorldPosition(v); fly(viewFor(v, 10)); highlight(id); return true; }
            return false;
        };
        document.getElementById('voCtrls').addEventListener('click', e => {
            const b = e.target.closest('[data-cam]'); if (!b) return;
            const k = b.dataset.cam;
            if (k === 'in' || k === 'out') fly({ pos: cam.position.clone(), target: controls.target.clone(), zoom: Math.min(4.5, Math.max(0.25, cam.zoom * (k === 'in' ? 1.35 : 0.74))) });
            if (k === 'rot') { const t = controls.target.clone(), off = cam.position.clone().sub(t); az = Math.atan2(off.x, off.z) + Math.PI / 2; const r = Math.hypot(off.x, off.z); fly({ pos: new V3(t.x + Math.sin(az) * r, cam.position.y, t.z + Math.cos(az) * r), target: t, zoom: cam.zoom }); }
            if (k === 'home') home();
        });

        // ── Highlight / search ──
        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.7, 0.08, 8, 32), new THREE.MeshBasicMaterial({ color: 0xf59e0b }));
        ring.rotation.x = Math.PI / 2; ring.visible = false; scene.add(ring);
        let ringFor = 0;
        function highlight(id) { ringFor = id; VO.setActive(id); avatars.forEach(a => a.tagEl.classList.toggle('hl', a.p.id === id)); }
        function applySearch() {
            const q = VO.query();
            avatars.forEach(a => a.tagEl.classList.toggle('hl', (!!q && a.p.name.toLowerCase().includes(q)) || a.p.id === ringFor));
            if (!q) return;
            const hit = VO.visible().find(p => p.name.toLowerCase().includes(q));
            if (hit && applySearch.last !== hit.id) { applySearch.last = hit.id; VO.focusPerson(hit.id); }
        }
        let st = null;
        VO.onSearch = () => { clearTimeout(st); st = setTimeout(() => { applySearch.last = 0; applySearch(); }, 350); };

        // ── Pindot ──
        const ray = new THREE.Raycaster(), ptr = new THREE.Vector2();
        let downAt = null;
        const setPtr = e => { const r = renderer.domElement.getBoundingClientRect(); ptr.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1); ray.setFromCamera(ptr, cam); };
        renderer.domElement.addEventListener('pointerdown', e => { downAt = [e.clientX, e.clientY]; });
        renderer.domElement.addEventListener('pointerup', e => {
            if (!downAt || Math.hypot(e.clientX - downAt[0], e.clientY - downAt[1]) > 6) return;
            setPtr(e);
            const hit = ray.intersectObjects(pickables, false)[0];
            if (hit && hit.object.userData.pid) { highlight(hit.object.userData.pid); VO.openCard(hit.object.userData.pid); }
        });
        renderer.domElement.addEventListener('pointermove', e => { if (e.buttons) return; setPtr(e); renderer.domElement.style.cursor = ray.intersectObjects(pickables, false)[0] ? 'pointer' : 'grab'; });
        labelsEl.addEventListener('click', e => { const g = e.target.closest('[data-room-go]'); if (g) VO.focusRoom(g.dataset.roomGo); });

        // ── Loop ──
        function resize() {
            const w = stage.clientWidth, h = stage.clientHeight;
            renderer.setSize(w, h, false); renderer.domElement.style.width = w + 'px'; renderer.domElement.style.height = h + 'px';
            labelR.setSize(w, h);
            const a = w / h, v = 11; cam.left = -v * a; cam.right = v * a; cam.top = v; cam.bottom = -v; cam.updateProjectionMatrix();
        }
        new ResizeObserver(resize).observe(stage);
        resize();
        const loopState = { far: undefined };
        let last = performance.now();
        function loop(now) {
            requestAnimationFrame(loop);
            if (document.hidden) { last = now; return; }
            const dt = Math.min(0.25, (now - last) / 1000); last = now;   // mabagal na device: tuloy pa rin ang lakad
            if (camState.t < 1 && camState.to) {
                camState.t = Math.min(1, camState.t + dt * 1.8);
                const k = 1 - Math.pow(1 - camState.t, 3);
                cam.position.lerpVectors(camState.from.pos, camState.to.pos, k);
                controls.target.lerpVectors(camState.from.target, camState.to.target, k);
                cam.zoom = camState.from.zoom + (camState.to.zoom - camState.from.zoom) * k; cam.updateProjectionMatrix();
            }
            controls.update();
            cutaway(false);
            schedule(now);
            avatars.forEach(a => { step(a, dt); pose(a, now / 1000 + a.t0); });
            doorsTick(dt);
            const far = cam.zoom < 0.75;
            if (far !== loopState.far) { loopState.far = far; avatars.forEach(a => a.tagEl.classList.toggle('far', far)); offTags.forEach(t => t.classList.toggle('far', far)); signs.forEach(t => t.classList.toggle('far', far)); }
            if (ringFor) { const a = avatars.get(ringFor); ring.visible = !!a; if (a) { ring.position.set(a.g.position.x, 0.12, a.g.position.z); ring.scale.setScalar(1 + Math.sin(now / 200) * 0.08); } }
            renderer.render(scene, cam);
            labelR.render(scene, cam);
        }
        VO.onChange(() => { avatars.forEach(a => { clearTimeout(a.talkT); clearTimeout(a.sayT); }); build(); F('voLoading').classList.add('hidden'); });
        requestAnimationFrame(loop);
    </script>

</body>

</html>