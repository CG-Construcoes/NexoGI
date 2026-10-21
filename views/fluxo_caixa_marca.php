<?php // Marca d'agua do Acompanhamento Financeiro ("em fase de testes") ?>
<div style="position:fixed; inset:0; z-index:900; pointer-events:none;
            overflow:hidden" aria-hidden="true">
    <?php for ($w = -3; $w <= 3; $w++): ?>
    <div style="position:absolute; left:50%; top:<?= 50 + $w * 22 ?>%; width:160%;
                transform:translate(-50%,-50%) rotate(-30deg); text-align:center;
                font-size:42px; font-weight:700; color:rgba(120,120,120,.14);
                white-space:nowrap; letter-spacing:5px">Relatório em fase de testes</div>
    <?php endfor; ?>
</div>
