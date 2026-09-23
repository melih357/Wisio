<?php ?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wisio — Sorunu yaz, doğru kişiyi bul.</title><link rel="icon" href="assets/img/wisio-icon.png"><link rel="stylesheet" href="assets/css/app.css"></head>
<body>
<div id="toast"></div>
<header class="topbar"><a class="brand" href="#home"><img src="assets/img/wisio-icon.png"><span>Wisio</span></a><nav><a href="#home">Ana Sayfa</a><a href="#questions">Sorular</a><a href="#cards">Bilgi Kartları</a><a href="#communities">Topluluklar</a><a href="#experts">Bilenler</a></nav><div id="authArea"></div></header>
<main id="app"><section class="loading">Wisio yükleniyor…</section></main>
<footer><div><b>Wisio</b><span>Sorunu yaz, doğru kişiyi bul.</span></div><small>Topluluk hafızası ve doğru insanı bulma platformu.</small></footer>
<div class="modal" id="modal"><div class="modal-card"><button class="close" onclick="closeModal()">×</button><div id="modalBody"></div></div></div>
<script>window.API='../backend/api.php';</script><script src="assets/js/app.js"></script>
</body></html>