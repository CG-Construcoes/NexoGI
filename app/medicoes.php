<?php
declare(strict_types=1);

/**
 * Engenharia > Previsoes > Medicoes — previsao de medicoes por centro de
 * custo. Centros vem do ERP via mp_centros(); as medicoes ficam em
 * msoft.PrevisaoMedicao e o total liquido e calculado por centro.
 */

/** Normaliza valor digitado ("1.234,56" / "1234.56" / negativo) para float. */
function med_valor($v): float
{
    $s = trim((string) $v);
    if ($s === '') {
        throw new InvalidArgumentException('Informe o valor liquido.');
    }
    $s = str_replace(' ', '', $s);
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    if (!is_numeric($s)) {
        throw new InvalidArgumentException('Valor liquido invalido.');
    }
    return (float) $s;
}

/** Normaliza uma data YYYY-MM-DD; vazio devolve null (ou erro se obrigatoria). */
function med_data($v, bool $obrigatoria, string $campo): ?string
{
    $s = trim((string) $v);
    if ($s === '') {
        if ($obrigatoria) {
            throw new InvalidArgumentException("Informe $campo.");
        }
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) {
        throw new InvalidArgumentException("$campo invalida.");
    }
    return $s;
}

/** Extrai e valida o Centro_CodC a partir de "CODC - Nome" ou "CODC". */
function med_codc($v): string
{
    $codc = strtoupper(trim((string) $v));
    if (preg_match('/^([A-Z0-9]{9})/', $codc, $m)) {
        $codc = $m[1];
    }
    if (!isset(mp_centros()[$codc])) {
        throw new InvalidArgumentException('Selecione um centro de custo da lista.');
    }
    return $codc;
}

/** Centros incluidos na previsao, com qtde de medicoes e total liquido. */
function med_listar(): array
{
    $rows = query("SELECT c.PMedC_Id, RTRIM(c.Centro_CodC) Centro_CodC,
                          (SELECT COUNT(*) FROM msoft.PrevisaoMedicao m
                           WHERE m.PMedC_Id = c.PMedC_Id) QtdeMedicoes,
                          (SELECT ISNULL(SUM(m.Med_Valor), 0)
                           FROM msoft.PrevisaoMedicao m
                           WHERE m.PMedC_Id = c.PMedC_Id) TotalLiquido
                   FROM msoft.PrevisaoMedicaoCentro c
                   ORDER BY c.Centro_CodC");
    $labels = mp_centros();
    foreach ($rows as &$r) {
        $r['Rotulo'] = $labels[$r['Centro_CodC']] ?? $r['Centro_CodC'];
    }
    usort($rows, fn($a, $b) => strcasecmp($a['Rotulo'], $b['Rotulo']));
    return $rows;
}

function med_get_centro(int $id): ?array
{
    $rows = query('SELECT PMedC_Id, RTRIM(Centro_CodC) Centro_CodC
                   FROM msoft.PrevisaoMedicaoCentro WHERE PMedC_Id = ?', [$id]);
    if (!$rows) {
        return null;
    }
    $rows[0]['Rotulo'] = mp_centros()[$rows[0]['Centro_CodC']] ?? $rows[0]['Centro_CodC'];
    return $rows[0];
}

function med_incluir_centro($v): int
{
    $codc = med_codc($v);
    if (query('SELECT 1 FROM msoft.PrevisaoMedicaoCentro WHERE Centro_CodC = ?', [$codc])) {
        throw new InvalidArgumentException('Este centro de custo ja esta na previsao.');
    }
    execute('INSERT INTO msoft.PrevisaoMedicaoCentro (Centro_CodC) VALUES (?)', [$codc]);
    return (int) query('SELECT MAX(PMedC_Id) id FROM msoft.PrevisaoMedicaoCentro')[0]['id'];
}

/** Remove o centro da previsao (as medicoes caem em cascade). */
function med_excluir_centro(int $id): void
{
    execute('DELETE FROM msoft.PrevisaoMedicaoCentro WHERE PMedC_Id = ?', [$id]);
}

/** Medicoes de um centro (mais recentes primeiro). */
function med_medicoes(int $pmedcId): array
{
    return query('SELECT PMed_Id, CONVERT(varchar(120), Med_Descricao) Med_Descricao,
                         Med_Valor,
                         CONVERT(varchar(10), Med_EmissaoNF, 120) Med_EmissaoNF,
                         CONVERT(varchar(10), Med_Previsao, 120) Med_Previsao
                  FROM msoft.PrevisaoMedicao
                  WHERE PMedC_Id = ? ORDER BY Med_Previsao, PMed_Id', [$pmedcId]);
}

function med_incluir_medicao(int $pmedcId, array $post): void
{
    $desc = trim((string) ($post['descricao'] ?? ''));
    if ($desc === '') {
        throw new InvalidArgumentException('Informe a descricao da medicao.');
    }
    if (mb_strlen($desc) > 120) {
        throw new InvalidArgumentException('Descricao excede 120 caracteres.');
    }
    $valor = med_valor($post['valor'] ?? '');
    $emissao = med_data($post['emissao'] ?? '', false, 'A data de emissao da NF');
    $previsao = med_data($post['previsao'] ?? '', true, 'A data de previsao de recebimento');
    execute('INSERT INTO msoft.PrevisaoMedicao
                 (PMedC_Id, Med_Descricao, Med_Valor, Med_EmissaoNF, Med_Previsao)
             VALUES (?, ?, ?, ?, ?)',
            [$pmedcId, $desc, $valor, $emissao, $previsao]);
}

function med_excluir_medicao(int $pmedcId, int $medId): void
{
    execute('DELETE FROM msoft.PrevisaoMedicao
             WHERE PMed_Id = ? AND PMedC_Id = ?', [$medId, $pmedcId]);
}
