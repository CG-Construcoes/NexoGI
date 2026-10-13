<?php
declare(strict_types=1);

/*
 * Relatorio Economico (Financeiro > Relatorios) — replica o modelo do ERP
 * alimentado pela spMovEconomicoDetalhe. A procedure devolve o detalhe por
 * movimento; aqui agregamos por Classe/Assunto/GO com tres colunas:
 * Acum. Anterior | Periodo | Acumulado.
 *
 * Secoes (por Classe_Tipo): 01/06 Receitas; 02/07/08 Despesas;
 * 09 Recursos em Sociedade; demais Outros Grupos. SALDO = Receitas+Despesas.
 */

const REL_ECON_TIPOS = [
    'receitas'  => ['Receitas',  ['01', '06']],
    'despesas'  => ['Despesas',  ['02', '07', '08']],
    'sociedade' => ['Recursos em Sociedade', ['09']],
    'outros'    => ['Outros Grupos', null],   // qualquer tipo restante
];

function relatorio_economico_centros(string $base, bool $incluirInativos = false): array
{
    $where = $incluirInativos ? '' : "WHERE ISNULL(RTRIM(Centro_Inativo), 'N') <> 'S'";
    return query(
        "SELECT Centro_Cod, Centro_Nome FROM dbo.Centro_de_Custo $where ORDER BY Centro_Nome",
        [], banco_da_base($base));
}

function relatorio_economico_nucleos(string $base): array
{
    try {
        return query(
            "SELECT Nucleo_Cod, Nucleo_Nome FROM dbo.Nucleo_de_Negocio ORDER BY Nucleo_Nome",
            [], banco_da_base($base));
    } catch (Throwable $t) {
        return [];
    }
}

function relatorio_economico_empresas(string $base): array
{
    try {
        return query(
            "SELECT Empresa_Cod, Empresa_Nome FROM dbo.Empresa ORDER BY Empresa_Cod",
            [], banco_da_base($base));
    } catch (Throwable $t) {
        return [['Empresa_Cod' => 'CGC', 'Empresa_Nome' => 'C. G. Construcoes']];
    }
}

function relatorio_economico_dados(string $base, array $o): array
{
    $banco = banco_da_base($base);
    // centros selecionados: lista de codigos (4 chars); vazio = todos
    $centrosSel = array_values(array_filter(array_map(
        fn($c) => preg_match('/^([0-9A-Za-z]{4})\b/', (string) $c, $m) ? $m[1] : '',
        (array) ($o['centros'] ?? []))));
    $nucleosSel = array_values(array_filter(array_map(
        fn($c) => preg_match('/^([0-9A-Za-z]{2})\b/', (string) $c, $m) ? $m[1] : '',
        (array) ($o['nucleos'] ?? []))));
    $empresa   = trim((string) $o['empresa']);
    if ($empresa === '') {
        throw new InvalidArgumentException('Selecione a empresa.');
    }

    // "Detalhar Apenas Periodo" dispensa o historico — consulta so o periodo.
    $apenasPeriodo = !empty($o['apenas_periodo']);
    $iniTotal = $apenasPeriodo || $o['data_ini'] === '' ? ($o['data_ini'] !== '' ? $o['data_ini'] : '1900-01-01')
                                                       : '1900-01-01';
    $fim   = $o['data_fim'] !== '' ? $o['data_fim'] : '2050-12-31';
    $corte = $o['data_ini'] !== '' ? $o['data_ini'] : '1900-01-01';
    $medicao   = !empty($o['medicao']) ? 'S' : 'N';
    $detCentro = !empty($o['det_centro']) ? 'S' : 'N';

    // parametros posicionais: 6=@Centro_Cod, 7=@Nucleo_Cod, 19=@Detalha_Centro, 21=@Medicao
    $sql = "EXEC spMovEconomicoDetalhe ?, ?, ?, NULL, NULL, ?, ?, NULL, NULL, NULL,
                   NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'S', ?, NULL, ?";
    $pdo = conexao($banco);
    $st = $pdo->prepare($sql);

    $classes = []; // Centro? => Classe => [titulo, tipo, assuntos[Ass => [titulo, ant, per, gos]]]
    // a procedure aceita um centro e um nucleo por execucao:
    // produto cartesiano dos selecionados (NULL = todos)
    $consultas = $centrosSel ?: [null];
    $nucleos   = $nucleosSel ?: [null];
    foreach ($consultas as $centroCod) {
    foreach ($nucleos as $nucleoCod) {
        $st->execute([$empresa, $iniTotal, $fim, $centroCod, $nucleoCod, $detCentro, $medicao]);
        while (true) {
            if ($st->columnCount() > 0) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $cla = trim((string) $r['Classe_Cod']);
                if ($cla === '') continue;
                $ass = trim((string) $r['Assunto_Cod']);
                $go  = trim((string) $r['GO_Cod']);
                $valor = (float) $r['EntItemApl_Valor'];
                $ant = substr((string) $r['Ent_DataEntrada'], 0, 10) < $corte;
                $cn  = $detCentro === 'S' ? trim((string) $r['Centro_CodC']) . ' ' . trim((string) $r['Centro_Nome'])
                                          : '';
                if (!isset($classes[$cn][$cla])) {
                    $classes[$cn][$cla] = ['titulo' => trim((string) $r['Classe_Titulo']),
                        'tipo' => trim((string) $r['Classe_Tipo']), 'assuntos' => []];
                }
                if (!isset($classes[$cn][$cla]['assuntos'][$ass])) {
                    $classes[$cn][$cla]['assuntos'][$ass] = [
                        'cod_ass' => $ass,
                        'titulo' => trim((string) $r['Assunto_Titulo']),
                        'ant' => 0.0, 'per' => 0.0, 'gos' => []];
                }
                if (!isset($classes[$cn][$cla]['assuntos'][$ass]['gos'][$go])) {
                    $classes[$cn][$cla]['assuntos'][$ass]['gos'][$go] = [
                        'cod' => $go,
                        'titulo' => trim((string) $r['GO_Titulo']),
                        'ant' => 0.0, 'per' => 0.0];
                }
                $classes[$cn][$cla]['assuntos'][$ass]['gos'][$go][$ant ? 'ant' : 'per'] += $valor;
                $classes[$cn][$cla]['assuntos'][$ass][$ant ? 'ant' : 'per'] += $valor;
                }
            }
            if (!$st->nextRowset()) break;
        }
        $st->closeCursor();
    }
    }

    // monta a estrutura de saida: um relatorio por centro (ou um so)
    $secoesSel = array_keys(array_filter([
        'receitas' => !empty($o['sec_receitas']), 'despesas' => !empty($o['sec_despesas']),
        'sociedade' => !empty($o['sec_sociedade']), 'outros' => !empty($o['sec_outros'])]));

    $blocos = [];
    foreach ($classes as $centroNome => $cls) {
        ksort($cls);
        $secoes = [];
        foreach (REL_ECON_TIPOS as $id => [$titulo, $tipos]) {
            if (!in_array($id, $secoesSel, true)) continue;
            $sec = ['id' => $id, 'titulo' => $titulo, 'classes' => [], 'ant' => 0.0, 'per' => 0.0];
            foreach ($cls as $cla => $c) {
                // modelo Informakon: classe 44 (D/C Indevidos) fica em Outros Grupos,
                // mesmo sendo Classe_Tipo 02 (Despesa operacional).
                // ($cla pode vir como int — chave numerica de array)
                $pertence = $tipos === null
                    ? (!in_array($c['tipo'], ['01', '06', '02', '07', '08', '09'], true) || (string) $cla === '44')
                    : (in_array($c['tipo'], $tipos, true) && (string) $cla !== '44');
                if (!$pertence) continue;
                ksort($c['assuntos']);
                $c['assuntos'] = array_values(array_filter($c['assuntos'],
                    fn($a) => $a['ant'] != 0.0 || $a['per'] != 0.0));
                foreach ($c['assuntos'] as $i => $a) {
                    ksort($a['gos']);
                    $c['assuntos'][$i]['cod'] = $cla . '.' . $a['cod_ass'];
                    $c['assuntos'][$i]['gos'] = array_values(array_filter($a['gos'],
                        fn($g) => $g['ant'] != 0.0 || $g['per'] != 0.0));
                }
                if (!$c['assuntos']) continue;
                $ant = array_sum(array_column($c['assuntos'], 'ant'));
                $per = array_sum(array_column($c['assuntos'], 'per'));
                $sec['classes'][] = ['cod' => $cla, 'titulo' => $c['titulo'],
                    'assuntos' => $c['assuntos'], 'ant' => $ant, 'per' => $per];
                $sec['ant'] += $ant;
                $sec['per'] += $per;
            }
            $secoes[] = $sec;
        }
        $blocos[$centroNome] = $secoes;
    }

    $centrosDesc = 'Todos';
    if ($centrosSel) {
        $descs = array_map('trim', (array) ($o['centros'] ?? []));
        $centrosDesc = count($descs) === 1 ? $descs[0] : count($descs) . ' centros selecionados';
    }
    $nucleosDesc = 'Todos';
    if ($nucleosSel) {
        $descs = array_map('trim', (array) ($o['nucleos'] ?? []));
        $nucleosDesc = count($descs) === 1 ? $descs[0] : count($descs) . ' nucleos selecionados';
    }
    return ['blocos' => $blocos, 'base' => $base, 'data_ini' => $o['data_ini'],
            'data_fim' => $fim, 'apenas_periodo' => $apenasPeriodo,
            'centros_desc' => $centrosDesc, 'centros_lista' => (array) ($o['centros'] ?? []),
            'nucleos_desc' => $nucleosDesc, 'nucleos_lista' => (array) ($o['nucleos'] ?? []),
            'mostrar_saldo' => !empty($o['sec_receitas']) && !empty($o['sec_despesas'])];
}
