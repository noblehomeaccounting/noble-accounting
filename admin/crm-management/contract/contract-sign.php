<?php
// contract-sign.php — PUBLIC na page ng client (route: contractsign?t=TOKEN)
// Walang login. Makikita ng client ang buong contract, pipirma gamit ang daliri / mouse,
// at kukuha ng picture ng valid ID. Gumagana sa cellphone.

include ROOT_PATH . '/network/connect.php';
include_once ROOT_PATH . '/admin/crm-management/contract/contract-template.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$token = (string) ($_GET['t'] ?? '');
$c = preg_match('/^[a-f0-9]{48}$/', $token) ? nhcLoad($conn, 'c.client_token = ?', 's', [$token]) : null;
$state = !$c ? 'invalid' : ($c['status'] === 'For Client Signing' ? 'sign' : 'closed');
$img = $c ? nhcImages($c) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Contract Signing · NobleHome</title>
    <style>
        :root { --amber: #b45309; --amber-d: #92400e; --ink: #111827; --muted: #6b7280; --line: #e5e7eb; --bg: #f1f5f9; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; -webkit-text-size-adjust: 100%; }
        .hazard { height: 6px; background: repeating-linear-gradient(-45deg, var(--amber) 0 10px, #1f2937 10px 20px); }
        .wrap { max-width: 860px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; margin-bottom: 14px; }
        .card-h { padding: 12px 16px; border-bottom: 1px solid var(--line); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #4b5563; }
        .card-b { padding: 16px; }
        .top { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border-bottom: 1px solid var(--line); }
        .top b { display: block; font-size: 14px; }
        .top span { font-size: 12px; color: var(--muted); }
        .doc { max-height: 60vh; overflow-y: auto; padding: 20px 18px; -webkit-overflow-scrolling: touch; }
        .doc-note { font-size: 12px; color: var(--muted); padding: 10px 16px; border-top: 1px solid var(--line); background: #fafafa; }
        label.lbl { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .inp { width: 100%; height: 46px; border: 1px solid #d1d5db; border-radius: 10px; padding: 0 12px; font-size: 16px; outline: none; }
        .inp:focus { border-color: var(--amber); box-shadow: 0 0 0 3px #fef3c7; }
        .pad-box { position: relative; height: 190px; border: 2px dashed #d1d5db; border-radius: 12px; background: #fff; }
        .pad-box canvas { position: absolute; inset: 0; width: 100%; height: 100%; touch-action: none; border-radius: 12px; }
        .pad-hint { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 15px; pointer-events: none; }
        .pad-line { position: absolute; left: 18px; right: 18px; bottom: 38px; border-bottom: 1px solid #e5e7eb; pointer-events: none; }
        .link-btn { background: none; border: 0; color: var(--muted); font-size: 13px; font-weight: 600; padding: 8px 0; cursor: pointer; }
        .id-drop { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; border: 2px dashed #d1d5db; border-radius: 12px; padding: 18px; text-align: center; cursor: pointer; background: #fafafa; }
        .id-drop b { font-size: 14px; }
        .id-drop span { font-size: 12px; color: var(--muted); }
        .id-prev { display: none; margin-top: 10px; max-width: 100%; max-height: 220px; border-radius: 10px; border: 1px solid var(--line); }
        .chk { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; line-height: 1.4; cursor: pointer; }
        .chk input { width: 20px; height: 20px; margin-top: 1px; accent-color: var(--amber); flex-shrink: 0; }
        .btn { width: 100%; height: 50px; border: 0; border-radius: 12px; background: var(--amber); color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; }
        .btn:disabled { background: #d1d5db; color: #6b7280; cursor: not-allowed; }
        .btn.sec { background: #fff; color: var(--ink); border: 1px solid #d1d5db; }
        .msg { display: none; margin-top: 12px; padding: 12px 14px; border-radius: 10px; font-size: 14px; }
        .msg.err { display: block; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .confirm { display: none; margin-top: 12px; padding: 14px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; }
        .confirm p { margin: 0 0 10px; font-size: 14px; }
        .row2 { display: flex; gap: 10px; }
        .center { text-align: center; padding: 40px 20px; }
        .center .ic { width: 64px; height: 64px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 10px; }
        .space > * + * { margin-top: 16px; }
        <?= nhcCss() ?>
        .nhc-doc { font-size: 13.5px; }
        @media (max-width: 520px) { .doc { padding: 14px 12px; } .nhc-doc { font-size: 12.5px; } .nhc-sign td { display: block; width: 100%; } }
    </style>
</head>

<body>
    <div class="hazard"></div>
    <div class="top">
        <?php if (!empty($img['logo'])): ?><img src="<?= $img['logo'] ?>" alt="" style="height:38px"><?php endif; ?>
        <div><b>NobleHome Construction Corporation</b><span>Contract signing</span></div>
    </div>

    <div class="wrap">
        <?php if ($state === 'invalid'): ?>
            <div class="card center">
                <div class="ic" style="background:#fef2f2;color:#b91c1c">!</div>
                <h2 style="margin:0 0 6px;font-size:18px">This link is not valid</h2>
                <p style="margin:0;color:var(--muted);font-size:14px">Please ask your NobleHome sales representative for a new signing link.</p>
            </div>
        <?php elseif ($state === 'closed'): ?>
            <div class="card center">
                <div class="ic" style="background:#ecfdf5;color:#047857">✓</div>
                <h2 style="margin:0 0 6px;font-size:18px">This contract is already signed</h2>
                <p style="margin:0;color:var(--muted);font-size:14px">Thank you. If you need a copy, please contact your NobleHome sales representative.</p>
            </div>
        <?php else: ?>
            <div id="signArea">
                <div class="card">
                    <div class="card-h">Contract for <?= nhcEsc($c['project_name']) ?> · <?= nhcEsc($c['control_no']) ?></div>
                    <div class="doc" id="doc"><?= nhcRenderContract($c, $img) ?></div>
                    <div class="doc-note">Please read the whole contract. Scroll inside the box to see all pages.</div>
                </div>

                <form class="card" id="signForm" onsubmit="return false" autocomplete="off">
                    <div class="card-h">Sign the contract</div>
                    <div class="card-b space">
                        <div>
                            <label class="lbl" for="client_name">Your full name</label>
                            <input class="inp" id="client_name" name="client_name" maxlength="150" value="<?= nhcEsc($c['owner_name']) ?>" autocomplete="name">
                        </div>

                        <div>
                            <span class="lbl">Photo of your valid ID</span>
                            <label class="id-drop" for="id_photo">
                                <b id="idLabel">📷 Take a photo of your ID</b>
                                <span>Front side, clear and readable · JPG or PNG</span>
                            </label>
                            <input type="file" id="id_photo" name="id_photo" accept="image/*" capture="environment" style="display:none">
                            <img id="idPrev" class="id-prev" alt="ID preview">
                        </div>

                        <div>
                            <span class="lbl">Your signature</span>
                            <div class="pad-box">
                                <div class="pad-line"></div>
                                <canvas id="pad"></canvas>
                                <div class="pad-hint" id="padHint">Sign here with your finger or mouse</div>
                            </div>
                            <button type="button" class="link-btn" onclick="padClear()">✕ Clear signature</button>
                        </div>

                        <label class="chk"><input type="checkbox" id="agree" name="agree" value="1">
                            <span>I have read and I agree to all the terms of this Contract Agreement with NobleHome Construction Corporation.</span></label>

                        <div>
                            <button type="button" class="btn" id="btnSign" onclick="askConfirm(true)">Sign contract</button>
                            <div class="confirm" id="confirmBox">
                                <p><b>Submit your signature?</b><br>Once submitted, it can no longer be changed.</p>
                                <div class="row2">
                                    <button type="button" class="btn sec" onclick="askConfirm(false)">Go back</button>
                                    <button type="button" class="btn" id="btnSubmit" onclick="submitSign()">Yes, submit</button>
                                </div>
                            </div>
                            <div class="msg" id="msg"></div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card center" id="doneArea" style="display:none">
                <div class="ic" style="background:#ecfdf5;color:#047857">✓</div>
                <h2 style="margin:0 0 6px;font-size:18px">Thank you!</h2>
                <p style="margin:0;color:var(--muted);font-size:14px">Your signature has been received. NobleHome will complete the contract and send you a copy.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($state === 'sign'): ?>
        <script>
            const SIGN_URL = <?= json_encode(BASE_URL . '/contractsignajax') ?>;
            const TOKEN = <?= json_encode($token) ?>;

            // ── signature pad (touch + mouse) ──
            const pad = { has: false };
            function padInit() {
                const cv = document.getElementById('pad');
                const r = cv.getBoundingClientRect();
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                cv.width = r.width * ratio;
                cv.height = r.height * ratio;
                const ctx = cv.getContext('2d');
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a'; ctx.fillStyle = '#0f172a';
                Object.assign(pad, { cv, ctx, has: false, down: false, last: null });
                document.getElementById('padHint').style.display = '';
            }
            function padPos(e) { const b = pad.cv.getBoundingClientRect(); return { x: e.clientX - b.left, y: e.clientY - b.top }; }
            function padMark() { if (!pad.has) { pad.has = true; document.getElementById('padHint').style.display = 'none'; } }
            function padClear() { padInit(); }

            padInit();
            pad.cv.addEventListener('pointerdown', e => {
                e.preventDefault();
                pad.cv.setPointerCapture(e.pointerId);
                pad.down = true; pad.last = padPos(e);
                pad.ctx.beginPath(); pad.ctx.arc(pad.last.x, pad.last.y, 1.1, 0, Math.PI * 2); pad.ctx.fill();
                padMark();
            });
            pad.cv.addEventListener('pointermove', e => {
                if (!pad.down) return;
                const p = padPos(e);
                pad.ctx.beginPath(); pad.ctx.moveTo(pad.last.x, pad.last.y); pad.ctx.lineTo(p.x, p.y); pad.ctx.stroke();
                pad.last = p;
            });
            ['pointerup', 'pointercancel'].forEach(t => pad.cv.addEventListener(t, () => { pad.down = false; }));
            // Kapag umikot ang phone, magbabago ang laki → ulitin ang pirma
            let lastW = window.innerWidth;
            window.addEventListener('resize', () => { if (Math.abs(window.innerWidth - lastW) > 40) { lastW = window.innerWidth; padInit(); } });

            // ── ID photo ──
            document.getElementById('id_photo').addEventListener('change', function () {
                const f = this.files && this.files[0];
                const prev = document.getElementById('idPrev');
                if (!f) { prev.style.display = 'none'; return; }
                document.getElementById('idLabel').textContent = '✓ ID photo added — tap to retake';
                prev.src = URL.createObjectURL(f);
                prev.style.display = 'block';
            });

            function showMsg(text) {
                const m = document.getElementById('msg');
                m.textContent = text; m.className = 'msg err';
            }
            function validate() {
                if (document.getElementById('client_name').value.trim().length < 2) return 'Please type your full name.';
                if (!document.getElementById('id_photo').files.length) return 'Please take or upload a photo of your valid ID.';
                if (!pad.has) return 'Please sign in the signature box.';
                if (!document.getElementById('agree').checked) return 'Please confirm that you have read and agree to the contract.';
                return '';
            }
            function askConfirm(show) {
                document.getElementById('msg').className = 'msg';
                if (show) {
                    const err = validate();
                    if (err) { showMsg(err); return; }
                }
                document.getElementById('btnSign').style.display = show ? 'none' : '';
                document.getElementById('confirmBox').style.display = show ? 'block' : 'none';
            }

            async function submitSign() {
                const btn = document.getElementById('btnSubmit');
                btn.disabled = true; btn.textContent = 'Submitting…';
                try {
                    const fd = new FormData();
                    fd.append('t', TOKEN);
                    fd.append('client_name', document.getElementById('client_name').value.trim());
                    fd.append('agree', document.getElementById('agree').checked ? '1' : '');
                    fd.append('signature', pad.cv.toDataURL('image/png'));
                    fd.append('id_photo', await shrinkImage(document.getElementById('id_photo').files[0]));
                    const res = await fetch(SIGN_URL, { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) { showMsg(data.message || 'Something went wrong. Please try again.'); askConfirm(false); return; }
                    document.getElementById('signArea').style.display = 'none';
                    document.getElementById('doneArea').style.display = 'block';
                    window.scrollTo(0, 0);
                } catch (e) {
                    showMsg('Connection error. Please check your internet and try again.');
                    askConfirm(false);
                } finally {
                    btn.disabled = false; btn.textContent = 'Yes, submit';
                }
            }

            // Pinapaliit ang malaking photo bago i-upload (mabilis sa mobile data)
            function shrinkImage(file) {
                return new Promise(resolve => {
                    if (!file || !/^image\//.test(file.type) || file.size < 1.5 * 1024 * 1024) return resolve(file);
                    const img = new Image();
                    img.onload = () => {
                        const max = 1800, s = Math.min(1, max / Math.max(img.width, img.height));
                        const cv = document.createElement('canvas');
                        cv.width = Math.round(img.width * s); cv.height = Math.round(img.height * s);
                        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
                        cv.toBlob(b => resolve(b ? new File([b], 'id.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
                    };
                    img.onerror = () => resolve(file);
                    img.src = URL.createObjectURL(file);
                });
            }
        </script>
    <?php endif; ?>
</body>

</html>