<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?: 'Msoft') ?> · Msoft ERP</title>
    <link rel="stylesheet" href="<?= url('static/css/style.css') . '?v=' . filemtime(ROOT . '/public/static/css/style.css') ?>">
</head>
<body>
<?= $conteudo ?>
</body>
</html>
