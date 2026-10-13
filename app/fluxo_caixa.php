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

/** Centro_CodC dos centros ja associados ao grupo. */
function fc_centros_do_grupo(int $id): array
{
    return array_map(
        fn($r) => trim((string) $r['Centro_CodC']),
        query('SELECT RTRIM(Centro_CodC) Centro_CodC FROM msoft.FluxoCaixa_Centros
               WHERE FCGrupo_Id = ? ORDER BY Centro_CodC', [$id]));
}

/** Normaliza "CODC - Nome" ou "CODC" para o Centro_CodC; valida contra mp_centros(). */
function fc_codc(string $centro): string
{
    $codc = strtoupper(trim($centro));
    if (preg_match('/^([A-Z0-9]{9})/', $codc, $m)) {
        $codc = $m[1];
    }
    if (!isset(mp_centros()[$codc])) {
        throw new InvalidArgumentException('Selecione um centro de custo da lista.');
    }
    return $codc;
}

function fc_incluir_centro(int $id, string $centro): void
{
    $codc = fc_codc($centro);
    execute('IF NOT EXISTS (SELECT 1 FROM msoft.FluxoCaixa_Centros
                          WHERE FCGrupo_Id = ? AND Centro_CodC = ?)
             INSERT INTO msoft.FluxoCaixa_Centros (FCGrupo_Id, Centro_CodC) VALUES (?, ?)',
            [$id, $codc, $id, $codc]);
}

function fc_excluir_centro(int $id, string $centro): void
{
    execute('DELETE FROM msoft.FluxoCaixa_Centros WHERE FCGrupo_Id = ? AND Centro_CodC = ?',
            [$id, strtoupper(trim($centro))]);
}
