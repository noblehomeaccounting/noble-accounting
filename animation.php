<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>NobleHome</title>
  <link href="https://fonts.googleapis.com/css2?family=Saira:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --orange: #f7941d;
      box-sizing: border-box;
      padding-top: env(safe-area-inset-top, 0px);
      padding-bottom: env(safe-area-inset-bottom, 0px)
    }

    html,
    body {
      height: 100%;
      margin: 0
    }

    body {
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: Saira, system-ui, sans-serif
    }

    .stage {
      --h: #333;
      --smoke: #c4c4c4;
      width: min(88vw, 540px)
    }

    svg {
      display: block;
      width: 100%;
      height: auto;
      overflow: visible
    }

    .d {
      fill-opacity: 0;
      stroke-width: 6;
      stroke-linejoin: miter;
      stroke-dasharray: 1;
      stroke-dashoffset: 1;
      animation: draw 1.2s cubic-bezier(.6, 0, .3, 1) var(--d) forwards, fillin .7s ease var(--f) forwards
    }

    .o {
      fill: #f7941d;
      stroke: #f7941d
    }

    .k {
      fill: var(--h);
      stroke: var(--h)
    }

    .roof {
      transform-box: fill-box;
      transform-origin: 50% 100%;
      animation: draw 1.2s cubic-bezier(.6, 0, .3, 1) .7s forwards, fillin .7s ease 1.5s forwards, settle .6s ease 2.0s
    }

    .shine {
      animation: shine 1s cubic-bezier(.4, 0, .2, 1) 2.1s both
    }

    .smoke {
      fill: var(--smoke);
      opacity: 0;
      transform-box: fill-box;
      transform-origin: 50% 50%;
      animation: puff 2.4s ease-out both
    }

    .t {
      font-family: Saira, 'Segoe UI', Arial, sans-serif;
      font-size: 158px;
      fill: #ff9800;
      text-anchor: middle;
      opacity: 0;
      animation: letter .7s cubic-bezier(.2, .8, .2, 1) both
    }

    @keyframes draw {
      to {
        stroke-dashoffset: 0
      }
    }

    @keyframes fillin {
      to {
        fill-opacity: 1;
        stroke-opacity: 0
      }
    }

    @keyframes settle {
      40% {
        transform: scale(1.025)
      }

      100% {
        transform: none
      }
    }

    @keyframes shine {
      from {
        transform: translateX(0) skewX(-18deg)
      }

      to {
        transform: translateX(2600px) skewX(-18deg)
      }
    }

    @keyframes puff {
      0% {
        opacity: 0;
        transform: translateY(0) scale(.6)
      }

      20% {
        opacity: .55
      }

      100% {
        opacity: 0;
        transform: translateY(-230px) scale(2.2)
      }
    }

    @keyframes letter {
      from {
        opacity: 0;
        transform: translateY(46px);
        filter: blur(8px)
      }

      to {
        opacity: 1;
        transform: none;
        filter: blur(0)
      }
    }

    @media (prefers-reduced-motion:reduce) {

      .d,
      .roof,
      .t {
        animation: none !important;
        fill-opacity: 1;
        stroke-opacity: 0;
        stroke-dashoffset: 0;
        opacity: 1
      }

      .shine,
      .smoke {
        display: none
      }
    }
  </style>
</head>

<body>
  <div class="stage" id="stage">
    <svg id="logo" viewBox="0 0 1930 1930" role="img" aria-label="NobleHome logo">
      <defs>
        <path id="pn"
          d="M267 828 L337 775 L788 1330 L790 456 L920 367 L922 1568 L792 1568 L397 1060 L397 1568 L267 1568Z" />
        <path id="pt" d="M410 722 L702 518 L706 1088Z" />
        <path id="ph"
          d="M1005 347 L1135 438 L1135 950 L1457 950 L1457 668 L1588 762 L1588 1568 L1457 1568 L1457 1080 L1135 1080 L1135 1568 L1005 1568Z" />
        <path id="pr"
          d="M70 695 L962 70 L1475 430 L1475 280 L1605 280 L1605 530 L1860 713 L1787 820 L962 232 L145 802Z" />
        <clipPath id="cp">
          <use href="#pn" />
          <use href="#pt" />
          <use href="#ph" />
          <use href="#pr" />
        </clipPath>
        <linearGradient id="g" x1="0" x2="1">
          <stop offset="0" stop-color="#fff" stop-opacity="0" />
          <stop offset=".5" stop-color="#fff" stop-opacity=".85" />
          <stop offset="1" stop-color="#fff" stop-opacity="0" />
        </linearGradient>
      </defs>

      <circle class="smoke" cx="1540" cy="250" r="20" style="animation-delay:2.5s" />
      <circle class="smoke" cx="1548" cy="250" r="16" style="animation-delay:3.0s" />
      <circle class="smoke" cx="1535" cy="250" r="22" style="animation-delay:3.5s" />

      <path class="d o" pathLength="1" style="--d:.1s;--f:1.3s"
        d="M267 828 L337 775 L788 1330 L790 456 L920 367 L922 1568 L792 1568 L397 1060 L397 1568 L267 1568Z" />
      <path class="d k" pathLength="1" style="--d:.5s;--f:1.4s" d="M410 722 L702 518 L706 1088Z" />
      <path class="d k" pathLength="1" style="--d:.35s;--f:1.4s"
        d="M1005 347 L1135 438 L1135 950 L1457 950 L1457 668 L1588 762 L1588 1568 L1457 1568 L1457 1080 L1135 1080 L1135 1568 L1005 1568Z" />
      <path class="d o roof" pathLength="1"
        d="M70 695 L962 70 L1475 430 L1475 280 L1605 280 L1605 530 L1860 713 L1787 820 L962 232 L145 802Z" />

      <g clip-path="url(#cp)">
        <rect class="shine" x="-500" y="0" width="260" height="1930" fill="url(#g)" />
      </g>

      <g>
        <text class="t" x="260" y="1855" style="animation-delay:2.3s">N</text>
        <text class="t" x="447" y="1855" style="animation-delay:2.38s">O</text>
        <text class="t" x="632" y="1855" style="animation-delay:2.46s">B</text>
        <text class="t" x="798" y="1855" style="animation-delay:2.54s">L</text>
        <text class="t" x="962" y="1855" style="animation-delay:2.62s">E</text>
        <text class="t" x="1129" y="1855" style="animation-delay:2.7s">H</text>
        <text class="t" x="1315" y="1855" style="animation-delay:2.78s">O</text>
        <text class="t" x="1518" y="1855" style="animation-delay:2.86s">M</text>
        <text class="t" x="1710" y="1855" style="animation-delay:2.94s">E</text>
      </g>
    </svg>
  </div>

  <script>
    // Auto redirect pagkatapos ng animation
    var HOME_URL = '<?= BASE_URL ?>/home1';
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ANIM_MS = reduceMotion ? 600 : 4200;

    setTimeout(function () {
      window.location.replace(HOME_URL);
    }, ANIM_MS);
  </script>
</body>

</html>