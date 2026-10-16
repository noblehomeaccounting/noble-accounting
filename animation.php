<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NobleHome Construction Corporation</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --orange: #F7941D;
      --dark: #333333;
      --blue: #8fa3b8;
    }

    html,
    body {
      margin: 0;
      height: 100%;
      background: #fff;
      overflow: hidden;
    }

    body {
      cursor: pointer;
    }

    /* ===== 1. CAUTION-STRIPE WIPE ===== */
    .wipe {
      position: fixed;
      top: -10%;
      left: -30%;
      width: 160%;
      height: 120%;
      z-index: 10;
      pointer-events: none;
      transform: translateX(-110%) skewX(-12deg);
      animation: wipe 1s cubic-bezier(.77, 0, .18, 1) forwards;
    }

    .w1 {
      background: repeating-linear-gradient(45deg, var(--orange) 0 60px, var(--dark) 60px 120px);
    }

    .w2 {
      background: var(--orange);
      animation-delay: .14s;
    }

    @keyframes wipe {
      from {
        transform: translateX(-110%) skewX(-12deg);
      }

      to {
        transform: translateX(110%) skewX(-12deg);
      }
    }

    /* ===== CAMERA ===== */
    .stage {
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .push {
      animation: push 5.5s linear 4.5s both;
    }

    .shake {
      animation: shake .5s linear 4.5s both;
    }

    .stage svg {
      display: block;
      width: min(64vmin, 430px);
      height: auto;
      overflow: visible;
    }

    @keyframes push {
      from {
        transform: scale(1);
      }

      to {
        transform: scale(1.07);
      }
    }

    @keyframes shake {
      0% {
        transform: translate(0, 0)
      }

      15% {
        transform: translate(0, 14px)
      }

      30% {
        transform: translate(-8px, -6px)
      }

      45% {
        transform: translate(6px, 4px)
      }

      60% {
        transform: translate(-3px, -2px)
      }

      80% {
        transform: translate(2px, 1px)
      }

      100% {
        transform: translate(0, 0)
      }
    }

    .orange {
      fill: var(--orange);
    }

    .dark {
      fill: var(--dark);
    }

    /* ===== 2. BLUEPRINT ===== */
    #grid {
      opacity: 0;
      animation: gridIn .7s ease .85s forwards, fadeAway .8s ease 5s forwards;
    }

    @keyframes gridIn {
      to {
        opacity: 1;
      }
    }

    @keyframes fadeAway {
      to {
        opacity: 0;
      }
    }

    .bp {
      fill: none;
      stroke: var(--blue);
      stroke-width: 5;
      stroke-linejoin: round;
      stroke-dasharray: 1;
      stroke-dashoffset: 1;
      animation: draw 1.1s cubic-bezier(.65, 0, .35, 1) forwards, fadeAway .8s ease 5s forwards;
      animation-delay: var(--d), 5s;
    }

    @keyframes draw {
      to {
        stroke-dashoffset: 0;
      }
    }

    /* foundation / ground line */
    #ground {
      transform-box: fill-box;
      transform-origin: 50% 50%;
      transform: scaleX(0);
      animation: groundIn .7s cubic-bezier(.16, 1, .3, 1) 1.6s forwards, fadeAway .8s ease 5s forwards;
    }

    @keyframes groundIn {
      to {
        transform: scaleX(1);
      }
    }

    /* ===== 3. WALLS RISE FROM FOUNDATION ===== */
    .wall {
      transform-box: fill-box;
      transform-origin: 50% 100%;
      animation: rise .75s cubic-bezier(.34, 1.3, .64, 1) both;
    }

    #n {
      animation-delay: 2.05s;
    }

    #h {
      animation-delay: 2.3s;
    }

    @keyframes rise {
      from {
        transform: scaleY(0);
      }

      to {
        transform: scaleY(1);
      }
    }

    #tri {
      transform-box: fill-box;
      transform-origin: 50% 50%;
      animation: panelIn .5s cubic-bezier(.16, 1, .3, 1) 2.85s both;
    }

    @keyframes panelIn {
      from {
        opacity: 0;
        transform: translateX(-260px);
      }

      to {
        opacity: 1;
        transform: none;
      }
    }

    /* ===== 4. CRANE LOWERS THE ROOF ===== */
    #load {
      transform-box: view-box;
      transform-origin: 962px -2400px;
      animation: lower 1.6s 2.9s both;
    }

    @keyframes lower {
      0% {
        transform: translateY(-2700px) rotate(0);
        animation-timing-function: cubic-bezier(.2, .6, .3, 1);
      }

      62% {
        transform: translateY(-40px) rotate(1.6deg);
        animation-timing-function: ease-in-out;
      }

      76% {
        transform: translateY(-18px) rotate(-1deg);
      }

      89% {
        transform: translateY(-6px) rotate(.4deg);
      }

      100% {
        transform: translateY(0) rotate(0);
      }
    }

    #hook {
      animation: retract 1s cubic-bezier(.5, 0, .75, 0) 4.8s both;
    }

    @keyframes retract {
      to {
        transform: translateY(-2900px);
      }
    }

    .ring {
      fill: none;
      opacity: 0;
      transform-box: fill-box;
      transform-origin: 50% 50%;
      animation: ring 1s cubic-bezier(.2, .8, .2, 1) forwards;
    }

    .r1 {
      stroke: var(--orange);
      animation-delay: 4.5s;
    }

    .r2 {
      stroke: var(--dark);
      animation-delay: 4.62s;
    }

    @keyframes ring {
      0% {
        transform: scale(.3);
        opacity: .8;
        stroke-width: 22;
      }

      100% {
        transform: scale(8);
        opacity: 0;
        stroke-width: 2;
      }
    }

    #glow {
      opacity: 0;
      transform-box: fill-box;
      transform-origin: 50% 50%;
      animation: glow 1.6s ease-out 4.5s forwards;
    }

    @keyframes glow {
      0% {
        opacity: 0;
        transform: scale(.5)
      }

      35% {
        opacity: .7;
        transform: scale(1.05)
      }

      100% {
        opacity: .35;
        transform: scale(1)
      }
    }

    /* ===== 5. NAME ===== */
    .letter {
      font-family: 'Exo 2', 'Segoe UI', sans-serif;
      font-weight: 500;
      font-size: 230px;
      fill: var(--orange);
      text-anchor: middle;
      transform-box: fill-box;
      transform-origin: 50% 60%;
      animation: stamp .42s cubic-bezier(.3, 1.4, .6, 1) both;
      animation-delay: calc(4.85s + var(--i) * .065s);
    }

    @keyframes stamp {
      0% {
        opacity: 0;
        transform: scale(1.9);
      }

      60% {
        opacity: 1;
        transform: scale(.95);
      }

      100% {
        opacity: 1;
        transform: scale(1);
      }
    }

    #tagbar {
      transform-box: fill-box;
      transform-origin: 50% 50%;
      animation: groundIn .6s cubic-bezier(.16, 1, .3, 1) 5.55s both;
      transform: scaleX(0);
    }

    #tagline {
      font-family: 'Exo 2', 'Segoe UI', sans-serif;
      font-weight: 600;
      font-size: 78px;
      fill: var(--dark);
      text-anchor: middle;
      letter-spacing: 18px;
      animation: tagIn .9s cubic-bezier(.16, 1, .3, 1) 5.7s both;
    }

    @keyframes tagIn {
      from {
        opacity: 0;
        letter-spacing: 70px;
      }

      to {
        opacity: 1;
        letter-spacing: 18px;
      }
    }

    /* ===== 6. GLASS SHINE ===== */
    .shine {
      animation: sweep 1.1s ease-in-out 6.3s forwards;
    }

    @keyframes sweep {
      from {
        transform: translateX(0);
      }

      to {
        transform: translateX(3200px);
      }
    }

    /* ===== EXIT ===== */
    body.leave .stage {
      animation: stageOut .7s cubic-bezier(.7, 0, .84, 0) forwards;
    }

    @keyframes stageOut {
      to {
        opacity: 0;
        transform: scale(1.15);
        filter: blur(3px);
      }
    }

    @media (prefers-reduced-motion: reduce) {

      *,
      *::before,
      *::after {
        animation: none !important;
      }

      .wipe,
      .ring,
      #glow,
      #grid,
      #blueprint,
      #ground,
      #hook {
        display: none;
      }

      #ground,
      #tagbar {
        transform: none;
      }
    }
  </style>
</head>

<body>

  <div class="wipe w1"></div>
  <div class="wipe w2"></div>

  <div class="stage">
    <div class="push">
      <div class="shake">
        <svg viewBox="0 0 1932 2080" xmlns="http://www.w3.org/2000/svg" aria-label="NobleHome Construction Corporation">
          <defs>
            <pattern id="gridPat" width="80" height="80" patternUnits="userSpaceOnUse">
              <path d="M80 0H0V80" fill="none" stroke="#dde5ee" stroke-width="3" />
            </pattern>
            <pattern id="gridPatBig" width="400" height="400" patternUnits="userSpaceOnUse">
              <path d="M400 0H0V400" fill="none" stroke="#c9d5e2" stroke-width="5" />
            </pattern>
            <radialGradient id="fadeEdge">
              <stop offset=".35" stop-color="#fff" />
              <stop offset="1" stop-color="#000" />
            </radialGradient>
            <mask id="gridMask">
              <rect x="-1100" y="-1100" width="4132" height="4280" fill="url(#fadeEdge)" />
            </mask>

            <radialGradient id="glowGrad">
              <stop offset="0" stop-color="#F7941D" stop-opacity=".35" />
              <stop offset=".6" stop-color="#F7941D" stop-opacity=".08" />
              <stop offset="1" stop-color="#F7941D" stop-opacity="0" />
            </radialGradient>
            <filter id="smokeBlur" x="-200%" y="-200%" width="500%" height="500%">
              <feGaussianBlur stdDeviation="22" />
            </filter>
            <clipPath id="logoClip">
              <use href="#roof" />
              <use href="#n" />
              <use href="#tri" />
              <use href="#h" />
            </clipPath>
            <linearGradient id="shineGrad" x1="0" x2="1">
              <stop offset="0" stop-color="#fff" stop-opacity="0" />
              <stop offset=".5" stop-color="#fff" stop-opacity=".75" />
              <stop offset="1" stop-color="#fff" stop-opacity="0" />
            </linearGradient>
          </defs>

          <!-- blueprint grid -->
          <g id="grid" mask="url(#gridMask)">
            <rect x="-1100" y="-1100" width="4132" height="4280" fill="url(#gridPat)" />
            <rect x="-1100" y="-1100" width="4132" height="4280" fill="url(#gridPatBig)" />
          </g>

          <circle id="glow" cx="966" cy="1000" r="1000" fill="url(#glowGrad)" />
          <circle class="ring r1" cx="962" cy="560" r="120" />
          <circle class="ring r2" cx="962" cy="560" r="120" />

          <!-- blueprint outlines (filled by script) -->
          <g id="blueprint"></g>

          <!-- foundation -->
          <rect id="ground" x="190" y="1568" width="1480" height="22" rx="4" fill="#333" />

          <!-- chimney smoke -->
          <g id="smoke" filter="url(#smokeBlur)"></g>

          <!-- walls -->
          <polygon id="n" class="wall orange"
            points="267,1568 267,827 338,775 785,1330 790,458 920,368 920,1568 793,1568 398,1060 397,1568" />
          <polygon id="h" class="wall dark"
            points="1005,348 1135,438 1135,950 1458,950 1458,670 1588,762 1588,1568 1458,1568 1458,1080 1135,1080 1135,1568 1005,1568" />
          <polygon id="tri" class="dark" points="412,720 702,518 706,1088" />

          <!-- roof carried by crane -->
          <g id="load">
            <polygon id="roof" class="orange"
              points="72,695 962,72 1472,432 1475,280 1605,280 1605,530 1860,715 1785,818 962,232 145,802" />
            <g id="hook">
              <line x1="962" y1="-3000" x2="962" y2="-170" stroke="#333" stroke-width="10" />
              <rect x="905" y="-190" width="114" height="90" rx="10" fill="#F7941D" stroke="#333" stroke-width="8" />
              <path d="M962,-100 L962,-50 A34,34 0 1 1 928,-16" fill="none" stroke="#333" stroke-width="16"
                stroke-linecap="round" />
              <line x1="962" y1="-30" x2="600" y2="325" stroke="#333" stroke-width="7" />
              <line x1="962" y1="-30" x2="1320" y2="325" stroke="#333" stroke-width="7" />
            </g>
          </g>

          <!-- glass shine -->
          <g clip-path="url(#logoClip)">
            <g transform="skewX(-20)">
              <rect class="shine" x="-900" y="0" width="440" height="1932" fill="url(#shineGrad)" />
            </g>
          </g>

          <g id="fx"></g>

          <!-- NOBLEHOME -->
          <text class="letter" x="262" y="1880" style="--i:0">N</text>
          <text class="letter" x="448" y="1880" style="--i:1">O</text>
          <text class="letter" x="632" y="1880" style="--i:2">B</text>
          <text class="letter" x="798" y="1880" style="--i:3">L</text>
          <text class="letter" x="965" y="1880" style="--i:4">E</text>
          <text class="letter" x="1130" y="1880" style="--i:5">H</text>
          <text class="letter" x="1315" y="1880" style="--i:6">O</text>
          <text class="letter" x="1518" y="1880" style="--i:7">M</text>
          <text class="letter" x="1720" y="1880" style="--i:8">E</text>

          <rect id="tagbar" x="716" y="1932" width="500" height="8" fill="#F7941D" />
          <text id="tagline" x="975" y="2040">CONSTRUCTION CORPORATION</text>
        </svg>
      </div>
    </div>
  </div>

  <script>
    const NEXT_URL = 'home1';   // login route
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const TOTAL = reduce ? 1500 : 11000;   // ms bago lumipat sa login
    const NS = 'http://www.w3.org/2000/svg';
    const fx = document.getElementById('fx');

    // ----- blueprint outlines: copy each logo shape as a thin line drawing -----
    const bp = document.getElementById('blueprint');
    [['roof', .9], ['n', 1.05], ['h', 1.15], ['tri', 1.3]].forEach(([id, d]) => {
      const p = document.createElementNS(NS, 'polygon');
      p.setAttribute('points', document.getElementById(id).getAttribute('points'));
      p.setAttribute('pathLength', '1');
      p.setAttribute('class', 'bp');
      p.style.setProperty('--d', d + 's');
      bp.appendChild(p);
    });

    // ----- dust / debris -----
    function burst(x, y, count, angleFrom, angleTo, dist, size, colors) {
      for (let i = 0; i < count; i++) {
        const r = document.createElementNS(NS, 'rect');
        const s = size * (0.5 + Math.random());
        r.setAttribute('x', -s / 2); r.setAttribute('y', -s / 2);
        r.setAttribute('width', s); r.setAttribute('height', s);
        r.setAttribute('fill', colors[Math.floor(Math.random() * colors.length)]);
        r.style.opacity = 0;
        fx.appendChild(r);
        const a = (angleFrom + Math.random() * (angleTo - angleFrom)) * Math.PI / 180;
        const d = dist * (0.5 + Math.random() * 0.7);
        const anim = r.animate([
          { transform: `translate(${x}px,${y}px) rotate(0deg) scale(1)`, opacity: 1 },
          { transform: `translate(${x + Math.cos(a) * d}px,${y + Math.sin(a) * d}px) rotate(${(Math.random() * 2 - 1) * 540}deg) scale(.2)`, opacity: 0 }
        ], { duration: 800 + Math.random() * 600, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'forwards' });
        anim.onfinish = () => r.remove();
      }
    }
    const DUST = ['#c9b8a3', '#a8a8a8', '#F7941D', '#333333'];

    // ----- chimney smoke -----
    const smoke = document.getElementById('smoke');
    function puff() {
      const c = document.createElementNS(NS, 'circle');
      c.setAttribute('r', 38 + Math.random() * 22);
      c.setAttribute('fill', Math.random() < .5 ? '#bdbdbd' : '#d6d6d6');
      c.style.opacity = 0;
      smoke.appendChild(c);
      const x0 = 1520 + Math.random() * 40, y0 = 300;
      const rise = 520 + Math.random() * 260, drift = 90 + Math.random() * 160;
      const sway = (Math.random() * 2 - 1) * 50;
      const a = c.animate([
        { transform: `translate(${x0}px,${y0}px) scale(.6)`, opacity: 0 },
        { transform: `translate(${x0 + sway}px,${y0 - rise * .3}px) scale(1.3)`, opacity: .55, offset: .2 },
        { transform: `translate(${x0 + drift * .5 - sway}px,${y0 - rise * .65}px) scale(2.3)`, opacity: .35, offset: .6 },
        { transform: `translate(${x0 + drift}px,${y0 - rise}px) scale(3.4)`, opacity: 0 }
      ], { duration: 2400 + Math.random() * 900, easing: 'cubic-bezier(.3,.6,.4,1)', fill: 'forwards' });
      a.onfinish = () => c.remove();
    }

    if (!reduce) {
      // walls rising kick up dust at the foundation
      setTimeout(() => burst(590, 1568, 12, 190, 350, 320, 18, DUST), 2150);
      setTimeout(() => burst(1300, 1568, 12, 190, 350, 320, 18, DUST), 2400);
      // roof lands
      setTimeout(() => {
        burst(120, 760, 16, 150, 260, 440, 26, DUST);
        burst(1820, 770, 16, -80, 30, 440, 26, DUST);
        burst(962, 140, 10, 200, 340, 300, 20, DUST);
      }, 4500);
      // smoke after the glass shine
      setTimeout(() => {
        puff(); puff();
        const t = setInterval(puff, 170);
        setTimeout(() => clearInterval(t), TOTAL - 8500);
      }, 7200);
    }

    let done = false;
    function go() {
      if (done) return;
      done = true;
      document.body.classList.add('leave');
      setTimeout(() => location.replace(NEXT_URL), 650);
    }
    setTimeout(go, TOTAL - 650);
    document.addEventListener('click', go);     // click / key to skip
    document.addEventListener('keydown', go);
  </script>
</body>

</html>