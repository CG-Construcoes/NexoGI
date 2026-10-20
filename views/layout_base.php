<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f2942">
    <title><?= e($titulo ?: 'Nouvellesobra') ?> · Nouvellesobra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('static/css/style.css') . '?v=' . filemtime(ROOT . '/public/static/css/style.css') ?>">
</head>
<body>
<?= $conteudo ?>
<script>
(function () {
    document.querySelectorAll('table.tabela').forEach(function (tbl) {
        if (tbl.parentElement && tbl.parentElement.classList.contains('tabela-responsiva')) return;
        var wrap = document.createElement('div');
        wrap.className = 'tabela-responsiva';
        tbl.parentNode.insertBefore(wrap, tbl);
        wrap.appendChild(tbl);
    });
})();
</script>
</body>
</html>
