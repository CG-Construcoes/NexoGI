<?php
// Exportacao do DRE para Excel — HTML com namespace x:num (abre no Excel
// preservando mesclas, subtotais e valores numericos). Chamada pelo case
// dre-* em public/index.php com os headers de download ja enviados.
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<style>
    table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    th, td { border: 0.5pt solid #d1d5db; padding: 3px 8px; }
    th { background: #eaf0f7; font-weight: 700; text-align: center; }
    .rel-econ-titulo { font-size: 14pt; font-weight: 700; margin-bottom: 2px; }
    .rel-econ-impresso { font-size: 9pt; color: #666; margin-bottom: 10px; }
    .col-valor { text-align: right; mso-number-format:"\#\.\#\#0\,00"; }
    .col-pct { text-align: right; color: #666; }
    tr.dre-n1 td { font-weight: 600; }
    tr.dre-sub td { font-weight: 700; background: #eaf0f7; }
    tr.dre-ll td { font-weight: 800; background: #d7e6f5; }
    tr.dre-go td { font-weight: 400; color: #374151; }
    tr.dre-go td:first-child { padding-left: 24px; }
    tr.dre-sub2 td { font-weight: 400; color: #6b7280; }
    tr.dre-sub2 td:first-child { padding-left: 44px; }
    tr.dre-nc td { font-weight: 600; color: #92400e; background: #fef3c7; }
    tr.dre-totgeral td { font-weight: 700; border-top: 1pt solid #9ca3af; }
    tr.dre-tempo td { font-size: 9pt; font-style: italic; color: #666; }
</style>
</head>
<body>
<?php require ROOT . '/views/dre_relatorio.php'; ?>
</body>
</html>
