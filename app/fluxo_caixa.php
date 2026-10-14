<?php
declare(strict_types=1);

/*
 * Fluxo de Caixa — Grupos de Centros de Custo (Financeiro > Fluxo de Caixa).
 * msoft.FluxoCaixa_Grupo  : FCGrupo_Id (identity), FCGrupo_Nome, FCGrupo_Descricao
 * msoft.FluxoCaixa_Centros: FCGrupo_Id + Centro_CodC (9 chars = Empresa+Filial+Centro,
 *                           mesma montagem de mp_centros(), reaproveitada aqui).
 */

function fc_listar(): array
{
    return query("SELECT g.FCGrupo_Id, RTRIM(g.FCGrupo_Nome) FCGrupo_Nome,
                         RTRIM(g.FCGrupo_Descricao) FCGrupo_Descricao,
                         (SELECT COUNT(*) FROM msoft.FluxoCaixa_Centros c
                          WHERE c.FCGrupo_Id = g.FCGrupo_Id) QtdeCentros
                  FROM msoft.FluxoCaixa_Grupo g
                  ORDER BY g.FCGrupo_Nome");
}

function fc_get(int $id): ?array
{
    $rows = query("SELECT FCGrupo_Id, RTRIM(FCGrupo_Nome) FCGrupo_Nome,
                          RTRIM(FCGrupo_Descricao) FCGrupo_Descricao
                   FROM msoft.FluxoCaixa_Grupo WHERE FCGrupo_Id = ?", [$id]);
    return $rows[0] ?? null;
}

/** Valida e normaliza os dados do formulario. Lanca InvalidArgumentException. */
function fc_validar(array $post): array
{
    $nome = trim((string) ($post['nome'] ?? ''));
    if ($nome === '') {
        throw new InvalidArgumentException('Informe o nome do grupo.');
    }
    if (strlen($nome) > 60) {
        throw new InvalidArgumentException('Nome excede 60 caracteres.');
    }
    $descricao = trim((string) ($post['descricao'] ?? ''));
    if (strlen($descricao) > 100) {
        throw new InvalidArgumentException('Descricao excede 100 caracteres.');
    }
    return ['nome' => $nome, 'descricao' => $descricao !== '' ? $descricao : null];
}

/** Insere o grupo e devolve o FCGrupo_Id gerado. */
function fc_inserir(array $d): int
{
    $rows = query('INSERT INTO msoft.FluxoCaixa_Grupo (FCGrupo_Nome, FCGrupo_Descricao)
                   OUTPUT INSERTED.FCGrupo_Id VALUES (?, ?)',
                  [$d['nome'], $d['descricao']]);
    return (int) ($rows[0]['FCGrupo_Id'] ?? 0);
}

function fc_atualizar(int $id, array $d): void
{
    execute('UPDATE msoft.FluxoCaixa_Grupo
             SET FCGrupo_Nome = ?, FCGrupo_Descricao = ? WHERE FCGrupo_Id = ?',
            [$d['nome'], $d['descricao'], $id]);
}

function fc_excluir(int $id): void
{
    execute('DELETE FROM msoft.FluxoCaixa_Grupo WHERE FCGrupo_Id = ?', [$id]);
}

/**
 * Centros de custo (dbMGA + dbConv), ATIVOS e INATIVOS:
 * Centro_CodC (9 chars = Empresa+Filial+Centro) => rotulo ("CODC - Nome (inativo)").
 * Diferente de mp_centros(), aqui entram os Centro_Inativo = 'S' tambem.
 */
function fc_centros(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach (['dbMGA', 'dbConv'] as $base) {
        try {
            $rows = query("SELECT RTRIM(Empresa_Cod) Empresa_Cod, RTRIM(Filial_Cod) Filial_Cod,
                                  RTRIM(Centro_Cod) Centro_Cod, RTRIM(Centro_Nome) Centro_Nome,
                                  ISNULL(RTRIM(Centro_Inativo), 'N') Centro_Inativo
                           FROM $base.dbo.Centro_de_Custo
                           ORDER BY Centro_Nome");
        } catch (Throwable $e) {
            continue;
        }
        foreach ($rows as $r) {
            $codc = strtoupper($r['Empresa_Cod'] . $r['Filial_Cod'] . $r['Centro_Cod']);
            if (strlen($codc) === 9) {
                $cache[$codc] = $codc . ' - ' . $r['Centro_Nome']
                    . ($r['Centro_Inativo'] === 'S' ? ' (inativo)' : '');
            }
        }
    }
    asort($cache);
    return $cache;
}

/** Centro_CodC dos centros ja associados ao grupo. */
function fc_centros_do_grupo(int $id): array
{
    return array_map(
        fn($r) => trim((string) $r['Centro_CodC']),
        query('SELECT RTRIM(Centro_CodC) Centro_CodC FROM msoft.FluxoCaixa_Centros
               WHERE FCGrupo_Id = ? ORDER BY Centro_CodC', [$id]));
}

/**
 * Centros JA agrupados em QUALQUER grupo:
 * Centro_CodC => ['FCGrupo_Id'=>int, 'FCGrupo_Nome'=>string].
 * Regra: um centro de custo nao pode se repetir entre grupos.
 */
function fc_centros_em_grupos(): array
{
    $map = [];
    foreach (query('SELECT RTRIM(c.Centro_CodC) Centro_CodC, c.FCGrupo_Id,
                           RTRIM(g.FCGrupo_Nome) FCGrupo_Nome
                    FROM msoft.FluxoCaixa_Centros c
                    JOIN msoft.FluxoCaixa_Grupo g ON g.FCGrupo_Id = c.FCGrupo_Id') as $r) {
        $map[$r['Centro_CodC']] = $r;
    }
    return $map;
}

/** Normaliza "CODC - Nome" ou "CODC" para o Centro_CodC; valida contra fc_centros(). */
function fc_codc(string $centro): string
{
    $codc = strtoupper(trim($centro));
    if (preg_match('/^([A-Z0-9]{9})/', $codc, $m)) {
        $codc = $m[1];
    }
    if (!isset(fc_centros()[$codc])) {
        throw new InvalidArgumentException('Selecione um centro de custo da lista.');
    }
    return $codc;
}

/**
 * Inclui centros no grupo: $selecionados = array de "CODC"/"CODC - Nome"
 * vindos dos checkboxes + opcionalmente um valor digitado.
 * Pula os ja agrupados (regra: centro nao se repete entre grupos).
 * Devolve ['ok' => qtd incluida, 'erros' => [mensagens]].
 */
function fc_incluir_centros(int $id, array $selecionados, string $digitado = ''): array
{
    $ocupados = fc_centros_em_grupos();
    $vistos = [];
    $ok = 0;
    $erros = [];
    if ($digitado !== '') {
        $selecionados[] = $digitado;
    }
    foreach ($selecionados as $c) {
        try {
            $codc = fc_codc((string) $c);
        } catch (InvalidArgumentException $e) {
            $erros[] = '"' . trim((string) $c) . '" nao e um centro de custo valido.';
            continue;
        }
        if (isset($vistos[$codc])) {
            continue;
        }
        $vistos[$codc] = true;
        if (isset($ocupados[$codc])) {
            $erros[] = ((int) $ocupados[$codc]['FCGrupo_Id'] === $id)
                ? "$codc ja esta neste grupo."
                : "$codc ja pertence ao grupo \"{$ocupados[$codc]['FCGrupo_Nome']}\".";
            continue;
        }
        execute('INSERT INTO msoft.FluxoCaixa_Centros (FCGrupo_Id, Centro_CodC) VALUES (?, ?)',
                [$id, $codc]);
        $ocupados[$codc] = ['FCGrupo_Id' => $id, 'FCGrupo_Nome' => ''];
        $ok++;
    }
    return ['ok' => $ok, 'erros' => $erros];
}

function fc_excluir_centro(int $id, string $centro): void
{
    execute('DELETE FROM msoft.FluxoCaixa_Centros WHERE FCGrupo_Id = ? AND Centro_CodC = ?',
            [$id, strtoupper(trim($centro))]);
}

/**
 * Dados do relatorio "Agrupamento de centros de custo":
 * lista de grupos, cada um com ['centros' => [rotulos ordenados]].
 */
function fc_relatorio(): array
{
    $grupos = [];
    foreach (fc_listar() as $g) {
        $grupos[(int) $g['FCGrupo_Id']] = $g + ['centros' => []];
    }
    $labels = fc_centros();
    foreach (query('SELECT FCGrupo_Id, RTRIM(Centro_CodC) Centro_CodC
                    FROM msoft.FluxoCaixa_Centros ORDER BY Centro_CodC') as $r) {
        $gid = (int) $r['FCGrupo_Id'];
        if (isset($grupos[$gid])) {
            $grupos[$gid]['centros'][] = [
                'codc'   => $r['Centro_CodC'],
                'rotulo' => $labels[$r['Centro_CodC']] ?? $r['Centro_CodC'],
            ];
        }
    }
    foreach ($grupos as &$g) {
        usort($g['centros'], fn($a, $b) => strcmp($a['rotulo'], $b['rotulo']));
    }
    return array_values($grupos);
}
