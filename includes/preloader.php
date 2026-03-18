<?php
/**
 * Global Page Preloader
 * Shows logo with gentle breathe + minimal dot pulse while the page loads.
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
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: opacity 0.45s ease;
}
#page-preloader.preloader-hidden {
    opacity: 0;
    pointer-events: none;
}
.preloader-logo {
    width: 72px;
    height: 72px;
    object-fit: contain;
    animation: preloader-breathe 2.4s ease-in-out infinite;
}
.preloader-dots {
    display: flex;
    gap: 6px;
    margin-top: 24px;
}
.preloader-dots span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #6366f1;
    opacity: 0.25;
    animation: preloader-dot 1.2s ease-in-out infinite;
}
.preloader-dots span:nth-child(2) { animation-delay: 0.2s; }
.preloader-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes preloader-breathe {
    0%, 100% { transform: scale(0.96); opacity: 0.7; }
    50% { transform: scale(1.04); opacity: 1; }
}
@keyframes preloader-dot {
    0%, 100% { opacity: 0.2; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.4); }
}
</style>
<div id="page-preloader">
    <img src="../assets/img/LOGO1.png" alt="iScan" class="preloader-logo">
    <div class="preloader-dots">
        <span></span>
        <span></span>
        <span></span>
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
