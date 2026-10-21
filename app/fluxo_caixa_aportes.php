<?php
declare(strict_types=1);

/*
 * Aportes a Retornar (Financeiro > Fluxo de Caixa > Aportes a Retornar).
 * Cadastro dos aportes por grupo de centros (msoft.FluxoCaixa_Grupo):
 *   msoft.GrupoCentro_Aporte: Aporte_Id, FCGrupo_Id, Aporte_Descricao,
 *   Aporte_Valor, Aporte_Desde, Aporte_AtualizadoEm.
 * A soma por grupo alimenta o item 2 do Acompanhamento Financeiro
 * (snapshot msoft.FluxoCaixa_Aportes — funcoes em fluxo_caixa_saldos.php).
 */

/** Grupos com quantidade e soma dos aportes cadastrados. */
function fca_listar_grupos(): array
{
    return query("SELECT g.FCGrupo_Id, RTRIM(g.FCGrupo_Nome) FCGrupo_Nome,
                         RTRIM(g.FCGrupo_Descricao) FCGrupo_Descricao,
                         (SELECT COUNT(*) FROM msoft.GrupoCentro_Aporte a
                          WHERE a.FCGrupo_Id = g.FCGrupo_Id) QtdeAportes,
                         (SELECT SUM(a.Aporte_Valor) FROM msoft.GrupoCentro_Aporte a
                          WHERE a.FCGrupo_Id = g.FCGrupo_Id) TotalAportes
                  FROM msoft.FluxoCaixa_Grupo g
                  LEFT JOIN msoft.FluxoCaixa_GrupoOrdem o ON o.FCGrupo_Id = g.FCGrupo_Id
                  ORDER BY ISNULL(o.Ordem, 2147483647), g.FCGrupo_Nome");
}

/** Aportes de um grupo (mais recentes primeiro). */
function fca_aportes_do_grupo(int $id): array
{
    return query("SELECT Aporte_Id, FCGrupo_Id,
                         CONVERT(varchar(4000), Aporte_Descricao) Aporte_Descricao,
                         Aporte_Valor,
                         CONVERT(varchar(10), Aporte_Desde, 120) Aporte_Desde,
                         CONVERT(varchar(19), Aporte_AtualizadoEm, 120) Aporte_AtualizadoEm
                  FROM msoft.GrupoCentro_Aporte
                  WHERE FCGrupo_Id = ? ORDER BY Aporte_Desde DESC, Aporte_Id DESC", [$id]);
}

/** Um aporte pelo id (para edicao). */
function fca_get(int $aporteId): ?array
{
    $rows = query("SELECT Aporte_Id, FCGrupo_Id,
                          CONVERT(varchar(4000), Aporte_Descricao) Aporte_Descricao,
                          Aporte_Valor,
                          CONVERT(varchar(10), Aporte_Desde, 120) Aporte_Desde
                   FROM msoft.GrupoCentro_Aporte WHERE Aporte_Id = ?", [$aporteId]);
    return $rows[0] ?? null;
}

/** Valida e normaliza o formulario de aporte. Lanca InvalidArgumentException. */
function fca_validar(array $post): array
{
    $descricao = trim((string) ($post['descricao'] ?? ''));
    if ($descricao === '') {
        throw new InvalidArgumentException('Informe a descricao do aporte.');
    }
    if (mb_strlen($descricao) > 120) {
        throw new InvalidArgumentException('Descricao excede 120 caracteres.');
    }
    $valorTxt = trim((string) ($post['valor'] ?? ''));
    if (!preg_match('/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$|^-?\d+(\.\d{1,2})?$/', $valorTxt)) {
        throw new InvalidArgumentException('Informe um valor valido para o aporte.');
    }
    $valor = fcs_valor($valorTxt);
    $desde = trim((string) ($post['desde'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        throw new InvalidArgumentException('Informe a data "Desde" do aporte.');
    }
    return ['descricao' => $descricao, 'valor' => $valor, 'desde' => $desde];
}

/** Insere o aporte no grupo e devolve o Aporte_Id gerado. */
function fca_inserir(int $grupoId, array $d): int
{
    $rows = query('INSERT INTO msoft.GrupoCentro_Aporte
                   (FCGrupo_Id, Aporte_Descricao, Aporte_Valor, Aporte_Desde,
                    Aporte_AtualizadoEm)
                   OUTPUT INSERTED.Aporte_Id VALUES (?, ?, ?, ?, GETDATE())',
                  [$grupoId, $d['descricao'], $d['valor'], $d['desde']]);
    return (int) ($rows[0]['Aporte_Id'] ?? 0);
}

function fca_atualizar(int $aporteId, array $d): void
{
    execute('UPDATE msoft.GrupoCentro_Aporte
             SET Aporte_Descricao = ?, Aporte_Valor = ?, Aporte_Desde = ?,
                 Aporte_AtualizadoEm = GETDATE()
             WHERE Aporte_Id = ?',
            [$d['descricao'], $d['valor'], $d['desde'], $aporteId]);
}

function fca_excluir(int $aporteId): void
{
    execute('DELETE FROM msoft.GrupoCentro_Aporte WHERE Aporte_Id = ?', [$aporteId]);
}
