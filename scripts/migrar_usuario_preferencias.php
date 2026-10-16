<?php
declare(strict_types=1);
/**
 * Cria a tabela de preferencias por usuario (rodar 1x, linha de comando):
 *   php scripts/migrar_usuario_preferencias.php
 *
 * msoft.UsuarioPreferencia: preferencias de tela salvas por usuario
 * (ex.: ordem das colunas da Agenda de Pagamentos). Chave = '<tela>.<pref>'.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.UsuarioPreferencia') IS NULL
    CREATE TABLE msoft.UsuarioPreferencia (
        Usuario   VARCHAR(120)  NOT NULL,
        Chave     VARCHAR(80)   NOT NULL,
        Valor     VARCHAR(4000) NULL,
        CONSTRAINT PK_UsuarioPreferencia PRIMARY KEY (Usuario, Chave)
    );
");
echo "Tabela msoft.UsuarioPreferencia verificada/criada.\n";
