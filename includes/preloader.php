<?php
/**
 * Global Page Preloader
 * Shows logo with scanning ripple rings + orbiting arc while the page loads.
 * Include this immediately after <body> in every page.
 * All CSS/JS is inline so it renders before external resources load.
 */
?>
<style>
#page-preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99999;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.5s ease;
    margin: 0;
    padding: 0;
}
#page-preloader.preloader-hidden {
    opacity: 0;
    pointer-events: none;
}
.preloader-scene {
    position: relative;
    width: 160px;
    height: 160px;
}
/* Logo - centered absolutely */
.preloader-logo {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 64px;
    height: 64px;
    transform: translate(-50%, -50%);
    object-fit: contain;
    z-index: 2;
    animation: preloader-breathe 2.4s ease-in-out infinite;
}
/* Ripple rings - sonar/scan effect */
.preloader-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 160px;
    height: 160px;
    margin-top: -80px;
    margin-left: -80px;
    border-radius: 50%;
    border: 2px solid rgba(99, 102, 241, 0.3);
    animation: preloader-ripple-expand 2.4s ease-out infinite;
    box-sizing: border-box;
}
.preloader-ripple:nth-child(2) { animation-delay: 0.8s; }
.preloader-ripple:nth-child(3) { animation-delay: 1.6s; }
/* Orbiting arc spinner */
.preloader-orbit {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 120px;
    height: 120px;
    margin-top: -60px;
    margin-left: -60px;
    border-radius: 50%;
    border: 3px solid transparent;
    border-top-color: #6366f1;
    border-right-color: rgba(99, 102, 241, 0.2);
    animation: preloader-spin 1.4s linear infinite;
    box-sizing: border-box;
}
/* Glowing dot at the leading edge of the arc */
.preloader-orbit::after {
    content: '';
    position: absolute;
    top: -2px;
    left: 50%;
    width: 8px;
    height: 8px;
    margin-left: -4px;
    background: #6366f1;
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.7), 0 0 20px rgba(99, 102, 241, 0.3);
}
/* Text */
.preloader-text {
    position: absolute;
    bottom: -40px;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'Inter', 'Poppins', system-ui, sans-serif;
    font-size: 12px;
    font-weight: 500;
    color: #9CA3AF;
    letter-spacing: 4px;
    text-transform: uppercase;
    animation: preloader-text-fade 2s ease-in-out infinite;
    white-space: nowrap;
}
@keyframes preloader-breathe {
    0%, 100% { transform: translate(-50%, -50%) scale(0.95); opacity: 0.75; }
    50% { transform: translate(-50%, -50%) scale(1.05); opacity: 1; }
}
@keyframes preloader-ripple-expand {
    0% { transform: scale(0.4); opacity: 0.5; }
    100% { transform: scale(1.4); opacity: 0; }
}
@keyframes preloader-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@keyframes preloader-text-fade {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}
</style>
<div id="page-preloader">
    <div class="preloader-scene">
        <!-- Ripple rings -->
        <div class="preloader-ripple"></div>
        <div class="preloader-ripple"></div>
        <div class="preloader-ripple"></div>
        <!-- Orbiting arc with dot -->
        <div class="preloader-orbit"></div>
        <!-- Logo -->
        <img src="../assets/img/LOGO1.png" alt="iScan" class="preloader-logo">
        <!-- Text -->
        <span class="preloader-text">Loading</span>
    </div>
</div>
<script>
window.addEventListener('load', function() {
    var el = document.getElementById('page-preloader');
    if (el) {
        el.classList.add('preloader-hidden');
        el.addEventListener('transitionend', function() { el.remove(); });
    }
});
setTimeout(function() {
    var el = document.getElementById('page-preloader');
    if (el) { el.classList.add('preloader-hidden'); }
}, 8000);
</script>
