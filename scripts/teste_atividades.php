<?php
declare(strict_types=1);
/**
 * Teste de ponta a ponta do Controle de Atividades (CLI):
 *   php scripts/teste_atividades.php [email_gestor] [email_colaborador]
 * Cria uma atividade de teste, exercita status/apontamento/comentario/
 * transferencia e exclui no final. Envia e-mail real de atribuicao
 * (EMAIL_MODO do .env).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$emailGestor = $argv[1] ?? 'misrain@cgconstrucoes.com';
$emailColab  = $argv[2] ?? 'misrain@cgconstrucoes.com';

function passo(string $txt): void { echo "==> $txt\n"; }
function ok(string $txt): void { echo "   OK  $txt\n"; }
function falha(string $txt): void { echo "   FAL $txt\n"; exit(1); }

// --- gestor logado ---
$_SESSION['usuario'] = $emailGestor;
$_SESSION['admin'] = true;
$eu = atv_eu();
$eu ?: falha("gestor '$emailGestor' nao encontrado/ativo");
ok('atv_eu: ' . $eu['Nome'] . ' (Funcao=' . var_export($eu['Funcao'], true) . ')');
ok('gestor: ' . var_export(atv_e_gestor($eu), true));

// --- cria ---
passo('criar atividade');
$colab = query('SELECT Id, Nome, Email FROM msoft.Usuarios WHERE Email = ?', [$emailColab])[0] ?? null;
$colab ?: falha("colaborador '$emailColab' nao encontrado");
$id = atv_criar([
    'titulo' => 'TESTE atividade automatizada',
    'orientacoes' => "Linha 1\nLinha 2",
    'visibilidade' => 'Individual',
    'prazo' => date('Y-m-d H:i', strtotime('+2 days')),
    'urgente' => 1,
    'referencia' => 'Solicitacao 2387/006',
    'responsavel' => (int) $colab['Id'],
    'participantes' => [(int) $eu['Id']],
], $eu);
$id > 0 ?: falha('criar retornou 0');
$a = atv_carregar($id);
ok("criada #$id status={$a['Status']} urgente=" . var_export(atv_urgente($a), true));
atv_pode_ver($a, $eu) ?: falha('criador nao ve a propria atividade');

// --- badge do responsavel ---
passo('badge');
$_SESSION['usuario'] = $emailColab;
$_SESSION['admin'] = false;
$euC = atv_eu();
$cor = atv_badge_cor();
ok("badge do colaborador: $cor (esperado vermelho: urgente)");

// --- colaborador: trabalhar (auto Em andamento) ---
passo('trabalhar (inicia relogio + status)');
$r = atv_trabalhar($id, $euC);
$a = atv_carregar($id);
ok("trabalhar=$r status={$a['Status']} iniciado=" . ($a['IniciadoEm'] ? 'sim' : 'nao'));

// --- parar ---
passo('parar relogio');
sleep(2);
$r = atv_trabalhar($id, $euC);
$trab = atv_trabalhado($id);
ok("trabalhar=$r trabalhado={$trab['total']}min");

// --- comentario ---
passo('comentar');
atv_comentar($id, 'Comentario de teste do colaborador', $euC);
ok('comentario gravado');

// --- status colaborador: Em andamento -> Aguardando retorno ---
passo('status colaborador');
atv_mudar_status($id, 'Aguardando retorno', $euC);
$a = atv_carregar($id);
ok("status={$a['Status']}");
// transicao proibida ao colaborador
try {
    atv_mudar_status($id, 'Concluida', $euC);
    falha('colaborador concluiu! regra falhou');
} catch (InvalidArgumentException $e2) {
    ok('colaborador bloqueado p/ Concluida: ' . $e2->getMessage());
}

// --- volta p/ andamento, transferir ---
passo('transferir');
atv_mudar_status($id, 'Em andamento', $euC);
atv_transferir($id, (int) $eu['Id'], $euC);
$parts = atv_participantes($id);
$resp = atv_responsavel($parts);
ok('novo responsavel: ' . ($resp['Nome'] ?? '?'));

// --- gestor conclui ---
passo('gestor conclui');
$_SESSION['usuario'] = $emailGestor;
$_SESSION['admin'] = true;
atv_mudar_status($id, 'Concluida', $eu);
$a = atv_carregar($id);
ok("status={$a['Status']} concluido=" . ($a['ConcluidoEm'] ? 'sim' : 'nao'));

// --- badge apos concluir (responsavel nao tem mais pendente) ---
$_SESSION['usuario'] = $emailColab;
$cor = atv_badge_cor();
ok("badge do colaborador agora: $cor");

// --- eventos ---
$evs = atv_eventos($id);
$tipos = array_column($evs, 'Tipo');
passo('linha do tempo: ' . count($evs) . ' eventos — ' . implode(',', array_unique($tipos)));

// --- listar ---
$_SESSION['usuario'] = $emailGestor;
$lista = atv_listar(['status' => ATV_STATUS, 'data_ini' => '', 'data_fim' => '',
    'escopo' => 'todas', 'resp' => 0, 'visib' => '', 'setor' => '',
    'urgente' => false, 'vencidas' => false, 'texto' => ''], atv_eu());
ok('listar (todas): ' . count($lista) . ' atividade(s)');

// --- excluir ---
atv_excluir($id, $eu);
ok("atividade #$id excluida (soft delete)");

echo "\n== TESTE CONCLUIDO ==\n";
