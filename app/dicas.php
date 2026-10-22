<?php
declare(strict_types=1);

/*
 * Dicas (Configuracoes > Dicas) — msoft.Dica.
 * Textos exibidos aleatoriamente no modal de progresso do calculo do
 * Acompanhamento Financeiro. As flags Sim/Nao por modulo direcionam a
 * dica para cada area do sistema.
 */

/** Modulos que podem receber dicas (coluna => rotulo). */
const DICAS_AREAS = [
    'Comercial'     => 'Comercial',
    'Contabilidade' => 'Contabilidade',
    'Engenharia'    => 'Engenharia',
    'Estoque'       => 'Estoque',
    'Financeiro'    => 'Financeiro',
    'Suprimentos'   => 'Suprimentos',
];

function dc_listar(): array
{
    return query('SELECT Dica_Id, RTRIM(CONVERT(varchar(200), Dica)) Dica,
                         RTRIM(Comercial) Comercial, RTRIM(Contabilidade) Contabilidade,
                         RTRIM(Engenharia) Engenharia, RTRIM(Estoque) Estoque,
                         RTRIM(Financeiro) Financeiro, RTRIM(Suprimentos) Suprimentos
                  FROM msoft.Dica ORDER BY Dica_Id');
}

function dc_get(int $id): ?array
{
    $rows = query('SELECT Dica_Id, RTRIM(CONVERT(varchar(200), Dica)) Dica,
                          RTRIM(Comercial) Comercial, RTRIM(Contabilidade) Contabilidade,
                          RTRIM(Engenharia) Engenharia, RTRIM(Estoque) Estoque,
                          RTRIM(Financeiro) Financeiro, RTRIM(Suprimentos) Suprimentos
                   FROM msoft.Dica WHERE Dica_Id = ?', [$id]);
    return $rows[0] ?? null;
}

/** Valida o formulario. Lanca InvalidArgumentException. */
function dc_validar(array $post): array
{
    $dica = trim((string) ($post['dica'] ?? ''));
    if ($dica === '') {
        throw new InvalidArgumentException('Informe o texto da dica.');
    }
    if (mb_strlen($dica) > 200) {
        throw new InvalidArgumentException('Dica excede 200 caracteres.');
    }
    $d = ['dica' => $dica];
    foreach (array_keys(DICAS_AREAS) as $area) {
        $d[$area] = !empty($post['area_' . strtolower($area)]) ? 'Sim' : 'Não';
    }
    return $d;
}

function dc_inserir(array $d): int
{
    $rows = query('INSERT INTO msoft.Dica
                   (Dica, Comercial, Contabilidade, Engenharia, Estoque, Financeiro, Suprimentos)
                   OUTPUT INSERTED.Dica_Id VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$d['dica'], $d['Comercial'], $d['Contabilidade'], $d['Engenharia'],
         $d['Estoque'], $d['Financeiro'], $d['Suprimentos']]);
    return (int) ($rows[0]['Dica_Id'] ?? 0);
}

function dc_atualizar(int $id, array $d): void
{
    execute('UPDATE msoft.Dica
             SET Dica = ?, Comercial = ?, Contabilidade = ?, Engenharia = ?,
                 Estoque = ?, Financeiro = ?, Suprimentos = ?
             WHERE Dica_Id = ?',
        [$d['dica'], $d['Comercial'], $d['Contabilidade'], $d['Engenharia'],
         $d['Estoque'], $d['Financeiro'], $d['Suprimentos'], $id]);
}

function dc_excluir(int $id): void
{
    execute('DELETE FROM msoft.Dica WHERE Dica_Id = ?', [$id]);
}

/**
 * Uma dica aleatoria. $area (ex.: 'financeiro') filtra pela flag do
 * modulo; sem dicas na area, sorteia entre todas as cadastradas.
 */
function dc_aleatoria(string $area = ''): ?string
{
    $col = null;
    foreach (DICAS_AREAS as $campo => $rotulo) {
        if (strtolower($campo) === strtolower($area)) {
            $col = $campo;
            break;
        }
    }
    $sql = 'SELECT TOP 1 RTRIM(CONVERT(varchar(200), Dica)) Dica
            FROM msoft.Dica' . ($col ? " WHERE $col = N'Sim'" : '')
            . ' ORDER BY NEWID()';
    $rows = query($sql);
    if (!$rows && $col) {
        $rows = query('SELECT TOP 1 RTRIM(CONVERT(varchar(200), Dica)) Dica
                       FROM msoft.Dica ORDER BY NEWID()');
    }
    return $rows[0]['Dica'] ?? null;
}
