<?php
// crmsupplier.php  —  Supplier & Products (Cutting Department)
// Dito inilalagay ang mga supplier at ang mga produkto nila (item code, description, unit).
// Sa BOM (ewoodfile.php), pipili na lang ng supplier at ng produkto mula rito.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING, ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$supAjaxUrl = BASE_URL . '/crmsupplierajax';
$bomPageUrl = BASE_URL . '/crmewood';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers &amp; Products</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-6 min-h-screen p-4 md:p-6 overflow-x-hidden transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="min-w-0">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Cutting Department</p>
                <h1 class="text-gray-900 text-lg font-semibold">Suppliers &amp; Products</h1>
                <p class="text-gray-400 text-[11px] mt-0.5">Add each supplier and the products they carry. In the BOM, you pick the supplier, then one of its products.</p>
            </div>
            <a href="<?= htmlspecialchars($bomPageUrl) ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50">
                <i class="fa-solid fa-file-invoice"></i> Back to BOM
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[340px_minmax(0,1fr)] gap-4 items-start">

            <!-- ═══ LEFT: suppliers ═══ -->
            <aside class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden min-w-0">
                <div class="p-3 border-b border-gray-100 space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1 min-w-0">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                            <input type="text" id="supSearch" placeholder="Search supplier…"
                                class="w-full h-9 bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:bg-white focus:border-amber-600">
                        </div>
                        <button type="button" id="supAddBtn"
                            class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                            <i class="fa-solid fa-plus text-[10px]"></i> Supplier
                        </button>
                    </div>
                    <label class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 cursor-pointer select-none">
                        <input type="checkbox" id="supShowHidden" class="accent-amber-700"> Show hidden suppliers
                    </label>
                </div>
                <div id="supList" class="max-h-[calc(100vh-15rem)] overflow-y-auto divide-y divide-gray-100">
                    <p class="px-4 py-10 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</p>
                </div>
                <p id="supCount" class="px-4 py-2 text-[11px] text-gray-400 border-t border-gray-100 bg-gray-50"></p>
            </aside>

            <!-- ═══ RIGHT: products of the selected supplier ═══ -->
            <section id="supDetail" class="min-w-0">
                <div class="bg-white border border-dashed border-gray-300 rounded-xl px-6 py-16 text-center">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-truck-field"></i></div>
                    <p class="text-sm font-semibold text-gray-700">Pick a supplier</p>
                    <p class="text-xs text-gray-400 mt-1">Its products will show here. Add a supplier first if the list is empty.</p>
                </div>
            </section>
        </div>

        <div id="supToast" class="fixed bottom-4 right-4 z-[10002] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ═══ SUPPLIER FORM (add / edit) ═══ -->
    <div id="supModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <form id="supForm" class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden" novalidate>
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-100">
                <p id="supFormTitle" class="text-sm font-semibold text-gray-800">Add supplier</p>
                <button type="button" data-close-sup aria-label="Close"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div class="p-5 space-y-3">
                <input type="hidden" name="id">
                <label class="block">
                    <span class="block text-[11px] font-semibold text-gray-600 mb-1">Supplier name <span class="text-red-500">*</span></span>
                    <input name="name" maxlength="150" placeholder="e.g. Wilcon Depot" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600">
                    <span data-err="name" class="hidden block text-[11px] text-red-600 mt-1"></span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="block text-[11px] font-semibold text-gray-600 mb-1">Contact person</span>
                        <input name="contact_person" maxlength="150" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[11px] font-semibold text-gray-600 mb-1">Contact no.</span>
                        <input name="contact_no" maxlength="50" inputmode="tel" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600">
                        <span data-err="contact_no" class="hidden block text-[11px] text-red-600 mt-1"></span>
                    </label>
                </div>
                <label class="block">
                    <span class="block text-[11px] font-semibold text-gray-600 mb-1">Address</span>
                    <input name="address" maxlength="255" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600">
                </label>
                <p id="supFormErr" class="hidden text-[11px] text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></p>
            </div>
            <div class="flex items-center justify-between gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <p id="supFormNote" class="text-[10.5px] text-gray-400"></p>
                <div class="flex gap-2">
                    <button type="button" data-close-sup class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                    <button type="submit" id="supFormSave" class="px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60">Save</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($supAjaxUrl) ?>;
            const F = id => document.getElementById(id);
            let suppliers = [], items = [], selId = null, itemQ = '';

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const memo = {
                get: k => { try { return localStorage.getItem('nh-sup-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-sup-' + k, v); } catch (e) { } },
                del: k => { try { localStorage.removeItem('nh-sup-' + k); } catch (e) { } },
            };
            function toast(message, type = 'success', duration = 3500) {
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('supToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }
            async function api(action, params = {}, post = false) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 20000);
                try {
                    const body = new URLSearchParams();
                    Object.entries(params).forEach(([k, v]) => body.append(k, v ?? ''));
                    const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${body}`;
                    const res = await fetch(url, post ? { method: 'POST', body, signal: ctrl.signal, cache: 'no-store' } : { signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text.replace(/^﻿/, '').trim()); }
                    catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? 'The server returned a page instead of data. Check that "crmsupplierajax" is in the router, or reload if your session expired.' : 'The server sent an unexpected response.'); }
                    if (!d.success) { const er = new Error(d.message || 'Something went wrong.'); er.field = d.field; throw er; }
                    return d;
                } catch (e) {
                    if (e.name === 'AbortError') throw new Error('The server took too long to respond.');
                    throw e;
                } finally { clearTimeout(timer); }
            }

            // ═════════════ SUPPLIER LIST ═════════════
            function renderList() {
                const q = F('supSearch').value.trim().toLowerCase();
                const showHidden = F('supShowHidden').checked;
                const list = suppliers.filter(s => (showHidden || s.is_active || s.id === selId)
                    && (!q || `${s.name} ${s.contact_person ?? ''}`.toLowerCase().includes(q)));
                const hiddenN = suppliers.filter(s => !s.is_active).length;
                F('supCount').textContent = `${suppliers.filter(s => s.is_active).length} active supplier${suppliers.length === 1 ? '' : 's'}${hiddenN ? ` · ${hiddenN} hidden` : ''}`;
                if (!list.length) {
                    F('supList').innerHTML = `<div class="px-4 py-10 text-center"><p class="text-xs font-semibold text-gray-600">${q ? 'No supplier matches.' : 'No suppliers yet.'}</p>${q ? '' : '<p class="text-[11px] text-gray-400 mt-1">Click "+ Supplier" to add the first one.</p>'}</div>`;
                    return;
                }
                F('supList').innerHTML = list.map(s => `
                    <button type="button" data-sid="${s.id}" class="w-full text-left flex items-center gap-3 px-4 py-3 transition ${s.id === selId ? 'bg-amber-50 shadow-[inset_3px_0_0_#b45309]' : 'hover:bg-gray-50'}">
                        <span class="w-9 h-9 rounded-lg ${s.is_active ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-400'} flex items-center justify-center shrink-0 text-xs font-bold">${esc(s.name.slice(0, 2).toUpperCase())}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold ${s.is_active ? 'text-gray-800' : 'text-gray-400 line-through'} truncate">${esc(s.name)}</span>
                            <span class="block text-[11px] text-gray-400 truncate">${s.active_count} product${s.active_count === 1 ? '' : 's'}${s.contact_person ? ' · ' + esc(s.contact_person) : ''}</span>
                        </span>
                        ${s.is_active ? '' : '<span class="text-[10px] font-semibold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5 shrink-0">Hidden</span>'}
                    </button>`).join('');
            }
            F('supSearch').addEventListener('input', renderList);
            F('supShowHidden').addEventListener('change', renderList);
            F('supList').addEventListener('click', e => {
                const b = e.target.closest('[data-sid]'); if (!b) return;
                selectSupplier(+b.dataset.sid);
            });

            async function loadSuppliers(keepSel = true) {
                try {
                    const d = await api('list');
                    suppliers = d.rows;
                    if (keepSel && selId && !suppliers.some(s => s.id === selId)) selId = null;
                    renderList();
                    if (!selId) renderDetail();
                } catch (e) {
                    F('supList').innerHTML = `<div class="px-4 py-10 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="supRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('supRetry').addEventListener('click', () => loadSuppliers());
                }
            }

            async function selectSupplier(id) {
                selId = id; itemQ = '';
                memo.set('sel', String(id));
                renderList();
                F('supDetail').innerHTML = `<div class="bg-white border border-gray-200 rounded-xl px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading products…</div>`;
                try {
                    const d = await api('items', { supplier_id: id });
                    if (selId !== id) return;
                    items = d.rows;
                    renderDetail();
                } catch (e) {
                    F('supDetail').innerHTML = `<div class="bg-white border border-gray-200 rounded-xl px-6 py-14 text-center text-xs text-red-600">${esc(e.message)}</div>`;
                }
            }

            // ═════════════ DETAIL: supplier header + products ═════════════
            const inp = 'w-full text-xs border border-gray-300 rounded-lg px-2.5 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600 bg-white';
            function renderDetail() {
                const s = suppliers.find(x => x.id === selId);
                if (!s) {
                    F('supDetail').innerHTML = `<div class="bg-white border border-dashed border-gray-300 rounded-xl px-6 py-16 text-center">
                        <div class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-truck-field"></i></div>
                        <p class="text-sm font-semibold text-gray-700">Pick a supplier</p>
                        <p class="text-xs text-gray-400 mt-1">Its products will show here. Add a supplier first if the list is empty.</p></div>`;
                    return;
                }
                let draft = null; try { draft = JSON.parse(memo.get('item-' + s.id) || 'null'); } catch (e) { }
                F('supDetail').innerHTML = `
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-base font-semibold text-gray-900 break-words">${esc(s.name)}</h2>
                                    ${s.is_active ? '<span class="text-[10px] font-semibold text-green-700 bg-green-50 border border-green-200 rounded-full px-2 py-0.5">Shown in BOM</span>' : '<span class="text-[10px] font-semibold text-gray-500 bg-gray-100 border border-gray-200 rounded-full px-2 py-0.5">Hidden from BOM</span>'}
                                </div>
                                <p class="text-[11px] text-gray-500 mt-1 break-words">${[s.contact_person && `<i class="fa-solid fa-user text-gray-300 mr-1"></i>${esc(s.contact_person)}`, s.contact_no && `<i class="fa-solid fa-phone text-gray-300 mr-1"></i>${esc(s.contact_no)}`, s.address && `<i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>${esc(s.address)}`].filter(Boolean).join('<span class="text-gray-300 mx-2">·</span>') || '<span class="text-gray-400">No contact details</span>'}</p>
                            </div>
                            <div id="supActions" class="flex flex-wrap items-center gap-1.5">
                                <button type="button" data-act="edit-sup" class="inline-flex items-center gap-1.5 h-8 px-3 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-pen text-[10px]"></i> Edit</button>
                                <button type="button" data-act="toggle-sup" class="inline-flex items-center gap-1.5 h-8 px-3 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid ${s.is_active ? 'fa-eye-slash' : 'fa-eye'} text-[10px]"></i> ${s.is_active ? 'Hide' : 'Show'}</button>
                                <button type="button" data-act="del-sup" class="inline-flex items-center gap-1.5 h-8 px-3 text-[11px] font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50"><i class="fa-solid fa-trash text-[10px]"></i> Delete</button>
                            </div>
                        </div>

                        <!-- Add product -->
                        <form id="itemAddForm" class="px-5 py-4 bg-amber-50/40 border-b border-gray-100" novalidate>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-plus mr-1 text-amber-700"></i>Add product</p>
                            <div class="grid grid-cols-2 sm:grid-cols-[140px_minmax(0,1fr)_100px_auto] gap-2 items-start">
                                <label class="min-w-0"><input name="item_code" maxlength="50" placeholder="Item code *" value="${esc(draft?.item_code || '')}" class="${inp} font-mono uppercase"><span data-err="item_code" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                                <label class="min-w-0 col-span-2 sm:col-span-1 row-start-2 sm:row-start-auto"><input name="description" maxlength="255" placeholder="Description *" value="${esc(draft?.description || '')}" class="${inp}"><span data-err="description" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                                <label class="min-w-0"><input name="unit" maxlength="30" placeholder="Unit" list="supUnits" value="${esc(draft?.unit || '')}" class="${inp}"></label>
                                <button type="submit" class="col-span-2 sm:col-span-1 h-[34px] px-4 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-plus text-[10px] mr-1"></i>Add</button>
                            </div>
                            <datalist id="supUnits"><option>PCS<option>BOX<option>SET<option>SHEET<option>ROLL<option>LM<option>SQM<option>KG<option>GAL<option>LTR<option>PAIR<option>LOT</datalist>
                        </form>

                        <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 border-b border-gray-100">
                            <p id="itemCount" class="text-[11px] text-gray-500"></p>
                            <div class="relative w-full sm:w-60">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                                <input type="text" id="itemSearch" value="${esc(itemQ)}" placeholder="Search code or description…" class="w-full h-8 bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:bg-white focus:border-amber-600">
                            </div>
                        </div>
                        <div id="itemList" class="divide-y divide-gray-100"></div>
                    </div>`;
                renderItems();
                bindDetail(s);
            }

            function itemRowHtml(it) {
                return `<div data-iid="${it.id}" class="item-row flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-1 px-5 py-2.5 ${it.is_active ? '' : 'bg-gray-50'}">
                    <span class="font-mono text-[12px] font-semibold ${it.is_active ? 'text-amber-800' : 'text-gray-400'} w-32 shrink-0 break-all">${esc(it.item_code)}</span>
                    <span class="min-w-0 flex-1 text-[13px] ${it.is_active ? 'text-gray-800' : 'text-gray-400 line-through'} break-words">${esc(it.description)}</span>
                    <span class="text-[11px] text-gray-500 w-16 shrink-0">${esc(it.unit || '—')}</span>
                    <span class="item-act flex items-center gap-1 shrink-0 ml-auto">
                        <button type="button" data-act="edit-item" title="Edit" class="w-7 h-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-amber-700 hover:bg-amber-50"><i class="fa-solid fa-pen text-[11px]"></i></button>
                        <button type="button" data-act="toggle-item" title="${it.is_active ? 'Hide from BOM' : 'Show in BOM'}" class="w-7 h-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100"><i class="fa-solid ${it.is_active ? 'fa-eye-slash' : 'fa-eye'} text-[11px]"></i></button>
                        <button type="button" data-act="del-item" title="Delete" class="w-7 h-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50"><i class="fa-solid fa-trash text-[11px]"></i></button>
                    </span>
                </div>`;
            }
            function renderItems() {
                const box = F('itemList'); if (!box) return;
                const on = items.filter(i => i.is_active).length, off = items.length - on;
                F('itemCount').innerHTML = `<b class="text-gray-700">${on}</b> product${on === 1 ? '' : 's'} shown in BOM${off ? ` · ${off} hidden` : ''}`;
                const q = itemQ.trim().toLowerCase();
                const list = items.filter(i => !q || `${i.item_code} ${i.description} ${i.unit ?? ''}`.toLowerCase().includes(q));
                box.innerHTML = list.length ? list.map(itemRowHtml).join('')
                    : `<p class="px-5 py-10 text-center text-xs text-gray-400">${q ? 'No product matches.' : 'No products yet. Add the first one above.'}</p>`;
            }

            function fieldErr(form, name, msg) {
                const el = form.querySelector(`[data-err="${name}"]`);
                if (el) { el.textContent = msg || ''; el.classList.toggle('hidden', !msg); }
                const i = form.querySelector(`[name="${name}"]`);
                if (i) { i.classList.toggle('border-red-400', !!msg); if (msg) i.setAttribute('aria-invalid', 'true'); else i.removeAttribute('aria-invalid'); }
            }

            function bindDetail(s) {
                // ── supplier actions (inline confirm para sa delete) ──
                F('supActions').addEventListener('click', async e => {
                    const b = e.target.closest('[data-act]'); if (!b) return;
                    const act = b.dataset.act;
                    if (act === 'edit-sup') return openSupForm(s);
                    if (act === 'toggle-sup') {
                        b.disabled = true;
                        try { const d = await api('set_supplier_active', { id: s.id, active: s.is_active ? 0 : 1 }, true); toast(d.message); await loadSuppliers(); renderDetail(); }
                        catch (er) { toast(er.message, 'error'); b.disabled = false; }
                        return;
                    }
                    if (act === 'del-sup') {
                        F('supActions').innerHTML = `<div class="flex flex-wrap items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5">
                            <span class="text-[11px] text-red-800">Delete <b>${esc(s.name)}</b> and its ${items.length} product${items.length === 1 ? '' : 's'}? Old BOMs keep their text.</span>
                            <button type="button" data-act="del-sup-yes" class="h-7 px-2.5 text-[11px] font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Delete</button>
                            <button type="button" data-act="del-sup-no" class="h-7 px-2.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-md">Cancel</button></div>`;
                        return;
                    }
                    if (act === 'del-sup-no') return renderDetail();
                    if (act === 'del-sup-yes') {
                        b.disabled = true; b.textContent = 'Deleting…';
                        try { const d = await api('delete_supplier', { id: s.id }, true); toast(d.message); selId = null; memo.del('sel'); await loadSuppliers(); renderDetail(); }
                        catch (er) { toast(er.message, 'error'); renderDetail(); }
                    }
                });

                // ── add product (may draft) ──
                const form = F('itemAddForm');
                const dkey = 'item-' + s.id;
                form.addEventListener('input', e => {
                    fieldErr(form, e.target.name, '');
                    memo.set(dkey, JSON.stringify({ item_code: form.item_code.value, description: form.description.value, unit: form.unit.value }));
                });
                form.addEventListener('submit', async e => {
                    e.preventDefault();
                    const code = form.item_code.value.trim(), desc = form.description.value.trim();
                    let bad = false;
                    if (!code) { fieldErr(form, 'item_code', 'Required.'); bad = true; }
                    if (!desc) { fieldErr(form, 'description', 'Required.'); bad = true; }
                    if (bad) return (code ? form.description : form.item_code).focus();
                    const btn = form.querySelector('[type=submit]'); btn.disabled = true;
                    try {
                        const d = await api('save_item', { supplier_id: s.id, item_code: code, description: desc, unit: form.unit.value.trim() }, true);
                        memo.del(dkey);
                        toast(d.message);
                        const r = await api('items', { supplier_id: s.id }); items = r.rows;
                        loadSuppliers();
                        form.reset(); form.item_code.value = ''; form.description.value = ''; form.unit.value = '';
                        renderItems();
                        form.item_code.focus();
                    } catch (er) {
                        if (er.field) fieldErr(form, er.field, er.message); else toast(er.message, 'error');
                    } finally { btn.disabled = false; }
                });

                F('itemSearch').addEventListener('input', e => { itemQ = e.target.value; renderItems(); });

                // ── product row actions: edit inline / hide / delete (inline confirm) ──
                F('itemList').addEventListener('click', async e => {
                    const b = e.target.closest('[data-act]'); if (!b) return;
                    const row = b.closest('.item-row'), id = +row.dataset.iid, it = items.find(x => x.id === id);
                    if (!it) return;
                    const act = b.dataset.act;
                    if (act === 'edit-item') {
                        row.outerHTML = `<form data-iid="${id}" class="item-edit grid grid-cols-2 sm:grid-cols-[140px_minmax(0,1fr)_100px_auto] gap-2 items-start px-5 py-2.5 bg-amber-50/50" novalidate>
                            <label class="min-w-0"><input name="item_code" maxlength="50" value="${esc(it.item_code)}" class="${inp} font-mono uppercase"><span data-err="item_code" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="min-w-0 col-span-2 sm:col-span-1 row-start-2 sm:row-start-auto"><input name="description" maxlength="255" value="${esc(it.description)}" class="${inp}"><span data-err="description" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="min-w-0"><input name="unit" maxlength="30" list="supUnits" value="${esc(it.unit || '')}" class="${inp}"></label>
                            <span class="col-span-2 sm:col-span-1 flex gap-1.5"><button type="submit" class="h-[34px] px-3 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60">Save</button>
                            <button type="button" data-act="cancel-edit" class="h-[34px] px-3 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></span></form>`;
                        F('itemList').querySelector(`form[data-iid="${id}"] [name=description]`).focus();
                        return;
                    }
                    if (act === 'toggle-item') {
                        b.disabled = true;
                        try { const d = await api('set_item_active', { id, active: it.is_active ? 0 : 1 }, true); it.is_active = it.is_active ? 0 : 1; toast(d.message); renderItems(); loadSuppliers(); }
                        catch (er) { toast(er.message, 'error'); b.disabled = false; }
                        return;
                    }
                    if (act === 'del-item') {
                        row.querySelector('.item-act').innerHTML = `<span class="text-[11px] text-red-700 mr-1">Delete?</span>
                            <button type="button" data-act="del-item-yes" class="h-7 px-2.5 text-[11px] font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Delete</button>
                            <button type="button" data-act="del-item-no" class="h-7 px-2.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-md">Cancel</button>`;
                        return;
                    }
                    if (act === 'del-item-no') return renderItems();
                    if (act === 'del-item-yes') {
                        b.disabled = true;
                        try { const d = await api('delete_item', { id }, true); toast(d.message); items = items.filter(x => x.id !== id); renderItems(); loadSuppliers(); }
                        catch (er) { toast(er.message, 'error'); renderItems(); }
                    }
                });
                F('itemList').addEventListener('click', e => { if (e.target.closest('[data-act="cancel-edit"]')) renderItems(); });
                F('itemList').addEventListener('input', e => { const f = e.target.closest('.item-edit'); if (f) fieldErr(f, e.target.name, ''); });
                F('itemList').addEventListener('submit', async e => {
                    const f = e.target.closest('.item-edit'); if (!f) return;
                    e.preventDefault();
                    const id = +f.dataset.iid, code = f.item_code.value.trim(), desc = f.description.value.trim();
                    let bad = false;
                    if (!code) { fieldErr(f, 'item_code', 'Required.'); bad = true; }
                    if (!desc) { fieldErr(f, 'description', 'Required.'); bad = true; }
                    if (bad) return;
                    const btn = f.querySelector('[type=submit]'); btn.disabled = true;
                    try {
                        const d = await api('save_item', { id, supplier_id: s.id, item_code: code, description: desc, unit: f.unit.value.trim() }, true);
                        toast(d.message);
                        const r = await api('items', { supplier_id: s.id }); items = r.rows;
                        renderItems();
                    } catch (er) {
                        if (er.field) fieldErr(f, er.field, er.message); else toast(er.message, 'error');
                        btn.disabled = false;
                    }
                });
            }

            // ═════════════ SUPPLIER FORM ═════════════
            const sf = F('supForm');
            function openSupForm(s) {
                sf.reset();
                ['name', 'contact_no'].forEach(n => fieldErr(sf, n, ''));
                F('supFormErr').classList.add('hidden');
                let draft = null;
                if (!s) { try { draft = JSON.parse(memo.get('new-sup') || 'null'); } catch (e) { } }
                const v = s || draft || {};
                sf.id.value = s ? s.id : '';
                sf.name.value = v.name || ''; sf.contact_person.value = v.contact_person || '';
                sf.contact_no.value = v.contact_no || ''; sf.address.value = v.address || '';
                F('supFormTitle').textContent = s ? 'Edit supplier' : 'Add supplier';
                F('supFormNote').textContent = draft ? 'Restored your unsaved draft.' : '';
                F('supModal').classList.remove('hidden'); F('supModal').classList.add('flex');
                setTimeout(() => sf.name.focus(), 50);
            }
            const closeSupForm = () => { F('supModal').classList.add('hidden'); F('supModal').classList.remove('flex'); };
            F('supAddBtn').addEventListener('click', () => openSupForm(null));
            document.querySelectorAll('[data-close-sup]').forEach(b => b.addEventListener('click', closeSupForm));
            F('supModal').addEventListener('click', e => { if (e.target === F('supModal')) closeSupForm(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSupForm(); });
            sf.addEventListener('input', e => {
                fieldErr(sf, e.target.name, ''); F('supFormErr').classList.add('hidden');
                if (!sf.id.value) { memo.set('new-sup', JSON.stringify({ name: sf.name.value, contact_person: sf.contact_person.value, contact_no: sf.contact_no.value, address: sf.address.value })); F('supFormNote').textContent = 'Draft saved on this device.'; }
            });
            sf.addEventListener('submit', async e => {
                e.preventDefault();
                if (!sf.name.value.trim()) { fieldErr(sf, 'name', 'Enter the supplier name.'); return sf.name.focus(); }
                const btn = F('supFormSave'); btn.disabled = true; btn.textContent = 'Saving…';
                try {
                    const d = await api('save_supplier', { id: sf.id.value, name: sf.name.value, contact_person: sf.contact_person.value, contact_no: sf.contact_no.value, address: sf.address.value }, true);
                    if (!sf.id.value) memo.del('new-sup');
                    toast(d.message);
                    closeSupForm();
                    await loadSuppliers();
                    selectSupplier(d.id);
                } catch (er) {
                    if (er.field) fieldErr(sf, er.field, er.message);
                    else { F('supFormErr').textContent = er.message; F('supFormErr').classList.remove('hidden'); }
                } finally { btn.disabled = false; btn.textContent = 'Save'; }
            });

            // ═════════════ INIT ═════════════
            loadSuppliers().then(() => {
                const want = +(memo.get('sel') || 0);
                if (want && suppliers.some(s => s.id === want)) selectSupplier(want);
            });
        })();
    </script>
</body>

</html>