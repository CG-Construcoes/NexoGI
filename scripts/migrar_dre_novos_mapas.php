<?php
/*
 * Migracao: novos mapeamentos em dbo.DRE_GR_CL (pedido 2026-10-23).
 * Pares GO+SubGO que hoje caem em "Nao classificado no DRE" passam a
 * classificar em linhas do formato DRE:
 *   0001 Venda de imoveis / 000001 Venda de imoveis      -> cf 1 (Receita Bruta)
 *   1201 Impostos Sobre Vendas / 000020 RET              -> cf 2 (Deducoes)
 *   0004 Receitas Financeiras / 000001 Recebimentos Fin. -> cf 10 (Result. Fin.)
 * Idempotente — pode rodar mais de uma vez (so insere o que nao existe).
 * Uso: php scripts/migrar_dre_novos_mapas.php
 */
require __DIR__ . '/../app/bootstrap.php';

$mapas = [
    ['nivel' => 1,  'dre' => 'Receita Operacional Bruta', 'cf' => 1,
     'go' => '0001  Venda de imoveis',      'sub' => '000001 Venda de imoveis'],
    ['nivel' => 2,  'dre' => 'Deduções das Vendas',       'cf' => 2,
     'go' => '1201  Impostos Sobre Vendas', 'sub' => '000020 RET (Regime Especial de Tributacao)'],
    ['nivel' => 10, 'dre' => 'Resultado Financeiro',      'cf' => 10,
     'go' => '0004  Receitas Financeiras',  'sub' => '000001 Recebimentos Financeiros'],
];

foreach ($mapas as $m) {
    $existe = query('SELECT COUNT(*) n FROM dbo.DRE_GR_CL
                     WHERE RTRIM(GO_Titulo) = ? AND RTRIM(SubGO_Titulo) = ?',
                    [$m['go'], $m['sub']])[0]['n'];
    if ($existe) {
        echo "ja existe: {$m['go']} | {$m['sub']}\n";
        continue;
    }
    execute('INSERT INTO dbo.DRE_GR_CL (Nivel, DRE, SubTotal, Cod_Formato, GO_Titulo, SubGO_Titulo)
             VALUES (?, ?, 0, ?, ?, ?)',
            [$m['nivel'], $m['dre'], $m['cf'], $m['go'], $m['sub']]);
    echo "inserido:  {$m['go']} | {$m['sub']} -> cf {$m['cf']} ({$m['dre']})\n";
}
echo "ok\n";
