<?php
declare(strict_types=1);
/**
 * Cria uma atividade de apresentacao da rotina (visibilidade Publica,
 * atribuida ao email passado). Uso: php scripts/seed_atividade_demo.php [email]
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$_SESSION['usuario'] = $argv[1] ?? 'misrain@cgconstrucoes.com';
$_SESSION['admin'] = true;
$eu = atv_eu();
if (!$eu) {
    exit("Usuario nao encontrado.\n");
}
$id = atv_criar([
    'titulo'       => 'Rotina de Atividades — primeiros passos',
    'orientacoes'  => "Atividade de apresentacao da nova rotina.\n\n"
        . "O gestor/coordenador cria atividades com responsavel, prazo e orientacoes.\n"
        . "O colaborador acompanha pelo menu Atividades e pelo icone no canto\n"
        . "inferior esquerdo (cinza = nada, amarelo = em aberto, vermelho = vencida/urgente).",
    'visibilidade' => 'Publica',
    'prazo'        => date('Y-m-d 18:00', strtotime('+3 days')),
    'urgente'      => 0,
    'referencia'   => '',
    'responsavel'  => (int) $eu['Id'],
    'participantes' => [],
], $eu);
echo "atividade demo criada: #$id\n";
