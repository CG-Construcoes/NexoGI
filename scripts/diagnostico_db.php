<?php
declare(strict_types=1);
/**
 * Diagnostico de ambiente/banco (rodar no servidor NexoGI):
 *   php scripts/diagnostico_db.php
 * Nao imprime senhas; mostra driver PDO, versoes, conectividade por banco
 * e uma chamada real da procedure usada pela Posicao de Contas.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

echo "PHP " . PHP_VERSION . "\n";
echo "extensoes: pdo_sqlsrv=" . (extension_loaded('pdo_sqlsrv') ? 'sim' : 'NAO')
    . " pdo_odbc=" . (extension_loaded('pdo_odbc') ? 'sim' : 'NAO')
    . " mbstring=" . (extension_loaded('mbstring') ? 'sim' : 'NAO') . "\n";

$host = (string) env('DB_SERVER');
echo "DB_SERVER=" . preg_replace('/[^0-9a-zA-Z.\-]/', '*', $host) . "\n";
echo "DB_NAME=" . env('DB_NAME', '(padrao CGGestao)') . "\n";
echo "DB_ERP_MGA=" . env('DB_ERP_MGA', '(padrao dbMGA)') . "  DB_ERP_CONV=" . env('DB_ERP_CONV', '(padrao dbConv)') . "\n\n";

function teste_banco(string $banco, string $tabela): void
{
    try {
        $n = query("SELECT COUNT(*) n FROM $tabela", [], $banco)[0]['n'];
        echo "[OK] $banco: $tabela tem $n linhas\n";
    } catch (Throwable $t) {
        echo "[FALHA] $banco: " . $t->getMessage() . "\n";
    }
}

teste_banco('CGGestao', 'msoft.Usuarios');
teste_banco('CGGestao', 'msoft.UsuarioGrupo');
teste_banco(env('DB_ERP_MGA', 'dbMGA'), 'dbo.Empresa');
teste_banco(env('DB_ERP_MGA', 'dbMGA'), 'dbo.AgenteFinanceiroConta');
teste_banco(env('DB_ERP_CONV', 'dbConv'), 'dbo.Empresa');
teste_banco(env('DB_ERP_CONV', 'dbConv'), 'dbo.AgenteFinanceiroConta');

echo "\n-- contas BC MGA (posicao-contas 14/10/2026) --\n";
try {
    $contas = query(
        "SELECT TOP 3 Conta.AgenteFinanceiroTipo_Cod, Conta.AgenteFinanceiro_Cod,
                Conta.AgeFin_ContaNumero, Conta.Empresa_Cod
         FROM dbo.AgenteFinanceiroConta Conta
         WHERE Conta.AgenteFinanceiroTipo_Cod = 'BC'
           AND (Conta.AgeFin_SuspendeImpressao <> 'S' OR Conta.AgeFin_SuspendeImpressao IS NULL)
           AND Conta.AgeFin_ImpDataSaldoRazao <= '2026-10-14'",
        [], env('DB_ERP_MGA', 'dbMGA'));
    echo "contas encontradas: " . count($contas) . "\n";
    if ($contas) {
        $c = $contas[0];
        echo "conta amostra: {$c['Empresa_Cod']} ag={$c['AgenteFinanceiro_Cod']} ct={$c['AgeFin_ContaNumero']}\n";
        echo "-- chamada spGetSaldoDiaAnteriorCon (batch DECLARE/EXEC/SELECT) --\n";
        $pdo = conexao(env('DB_ERP_MGA', 'dbMGA'));
        $st = $pdo->prepare(
            "DECLARE @s numeric(12,2), @d datetime;
             EXEC dbo.spGetSaldoDiaAnteriorCon ?,?,?,?,?,?, @s OUTPUT, @d OUTPUT;
             SELECT @s AS saldo, CONVERT(varchar(23), @d, 121) AS dt");
        $st->execute(['BC', trim($c['AgenteFinanceiro_Cod']), trim($c['AgeFin_ContaNumero']),
                      '2026-10-14', trim($c['Empresa_Cod']), null]);
        print_r($st->fetch(PDO::FETCH_ASSOC));
        $st->closeCursor();
    }
} catch (Throwable $t) {
    echo "[FALHA] " . $t->getMessage() . "\n";
}
echo "\nfim.\n";
