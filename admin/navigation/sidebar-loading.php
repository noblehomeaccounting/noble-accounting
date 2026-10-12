<?php
// nh-loader.php — NH fill loader (sumasabay sa totoong page load)
if (defined('NH_LOADER_INCLUDED')) {
    return;
}
define('NH_LOADER_INCLUDED', true);
?>

<!-- ===== NH FILL LOADER ===== -->
<div id="nh-loader" aria-label="Loading">
    <svg viewBox="0 0 1930 1640" xmlns="http://www.w3.org/2000/svg" role="img">
        <defs>
            <clipPath id="nhShape">
                <polygon
                    points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
                <polygon
                    points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
                <polygon points="410,722 702,518 706,1088" />
                <polygon
                    points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
            </clipPath>
            <!-- rising liquid with wavy top -->
            <clipPath id="nhLiquid">
                <path
                    d="M-400,0 q100,-45 200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 V2000 H-400 Z">
                    <animateTransform attributeName="transform" type="translate" additive="sum" dur="1.1s"
                        repeatCount="indefinite" values="0 0; 400 0" />
                </path>
            </clipPath>
        </defs>

        <!-- empty logo (no solid color) -->
        <g class="empty">
            <polygon points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
            <polygon
                points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
            <polygon points="410,722 702,518 706,1088" />
            <polygon
                points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
        </g>

        <!-- colored logo, only visible where the liquid is -->
        <g clip-path="url(#nhShape)">
            <g clip-path="url(#nhLiquid)">
                <rect x="0" y="0" width="1930" height="1640" class="o" />
                <polygon class="d" points="410,722 702,518 706,1088" />
                <polygon class="d"
                    points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
            </g>
        </g>
    </svg>
</div>

<style>
    #nh-loader {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(17, 17, 17, .55);
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity .5s ease, visibility .5s ease;
    }

    #nh-loader.nh-hide {
        opacity: 0;
        visibility: hidden
    }

    #nh-loader svg {
        width: min(160px, 42vw);
        height: auto;
        overflow: hidden
    }

    #nh-loader .empty {
        fill: rgba(255, 255, 255, .12);
        stroke: rgba(255, 255, 255, .4);
        stroke-width: 6;
        stroke-linejoin: round
    }

    #nh-loader .o {
        fill: #f79421
    }

    #nh-loader .d {
        fill: #333
    }
</style>

<script>
    // Loader fill follows the real page-load progress
    (function () {
        var loader = document.getElementById('nh-loader');
        var wave = document.querySelector('#nhLiquid path');
        if (!loader || !wave) return;

        var EMPTY = 1750, FULL = -80;   // liquid Y position: empty -> full
        var shown = 0, target = 0, done = false, start = Date.now();

        function paint(p) {
            wave.setAttribute('transform', 'translate(0 ' + (EMPTY + (FULL - EMPTY) * p / 100) + ')');
        }
        function bump(p) { if (p > target) target = p; }
        paint(0);

        // 1) HTML still downloading: creep slowly up to 35%
        var creep = setInterval(function () {
            bump(Math.min(35, (Date.now() - start) / 40));
        }, 100);

        // 2) HTML parsed: 60%, then each image loaded adds up to 95%
        function onReady() {
            bump(60);
            var imgs = Array.prototype.slice.call(document.images);
            var total = imgs.length, loaded = 0;
            if (!total) return;
            function tick() { loaded++; bump(60 + 35 * loaded / total); }
            imgs.forEach(function (img) {
                if (img.complete) tick();
                else {
                    img.addEventListener('load', tick);
                    img.addEventListener('error', tick);
                }
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady);
        else onReady();

        // 3) everything loaded: 100% then hide
        function finish() { done = true; bump(100); }
        window.addEventListener('load', finish);
        setTimeout(finish, 15000); // safety: never block the page forever

        // smooth the liquid toward the target
        (function frame() {
            shown += (target - shown) * 0.12;
            if (done && target - shown < 0.5) shown = 100;
            paint(shown);
            if (shown >= 100) {
                clearInterval(creep);
                setTimeout(function () {
                    loader.classList.add('nh-hide');
                    setTimeout(function () { loader.remove(); }, 600);
                }, 250);
                return;
            }
            requestAnimationFrame(frame);
        })();
    })();
</script>
<!-- ===== END NH FILL LOADER ===== -->