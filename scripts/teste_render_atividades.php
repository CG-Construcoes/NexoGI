<?php
declare(strict_types=1);
/** Renderiza views/atividades.php e views/atividade.php em CLI p/ pegar erros. */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
error_reporting(E_ALL & ~E_DEPRECATED);

$_SESSION['usuario'] = $argv[1] ?? 'misrain@cgconstrucoes.com';
$_SESSION['admin'] = true;
$_SESSION['csrf'] = 'x';
$eu = atv_eu();
if (!$eu) {
    exit("usuario nao encontrado\n");
}
$gestor = atv_e_gestor($eu);

// --- view lista ---
$f = ['data_ini' => '', 'data_fim' => '', 'escopo' => 'minhas', 'resp' => 0,
      'visib' => '', 'setor' => '', 'urgente' => false, 'vencidas' => false,
      'texto' => '', 'status' => array_values(array_diff(ATV_STATUS, ATV_STATUS_FINAIS))];
$linhas = atv_listar($f, $eu);
$vars = ['titulo' => 'x', 'aviso' => null, 'f' => $f, 'linhas' => $linhas,
    'erros' => [], 'gestor' => $gestor, 'eu' => $eu,
    'usuarios' => atv_usuarios(), 'setores' => atv_setores()];
extract($vars);
ob_start();
require ROOT . '/views/atividades.php';
$out = ob_get_clean();
echo 'atividades.php render: ' . strlen($out) . " bytes, " . count($linhas) . " linha(s)\n";

// --- view detalhe ---
$id = (int) (query('SELECT TOP 1 Atividade_Id FROM msoft.Atividades WHERE Excluida = 0')[0]['Atividade_Id'] ?? 0);
$a = atv_carregar($id);
$vars2 = ['titulo' => 'x', 'aviso' => null, 'nova' => false, 'a' => $a, 'eu' => $eu,
    'gestor' => $gestor,
    'parts' => atv_participantes($id), 'eventos' => atv_eventos($id),
    'aponts' => atv_apontamentos($id), 'trab' => atv_trabalhado($id),
    'apontMeu' => atv_apont_aberto($id, (int) $eu['Id']),
    'usuarios' => atv_usuarios(), 'setores' => atv_setores()];
extract($vars2, EXTR_SKIP);
ob_start();
require ROOT . '/views/atividade.php';
$out2 = ob_get_clean();
echo 'atividade.php (detalhe) render: ' . strlen($out2) . " bytes\n";

// --- view nova ---
$nova = true;
$a = null;
$parts = $eventos = $aponts = [];
$trab = ['porUsuario' => [], 'total' => 0];
$apontMeu = null;
ob_start();
require ROOT . '/views/atividade.php';
$out3 = ob_get_clean();
echo 'atividade.php (nova) render: ' . strlen($out3) . " bytes\n";

// --- badge ---
echo 'badge: ' . atv_badge_cor() . "\n";
echo "== RENDER OK ==\n";
