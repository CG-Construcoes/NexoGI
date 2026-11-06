<?php
/**
 * Renomeia a tela 'pedidos' para 'followup-pedidos' nas permissoes dos grupos
 * (roda 1x em cada ambiente; idempotente).
 * Uso: php scripts/migrar_tela_followup_pedidos.php
 */
require __DIR__ . '/../app/bootstrap.php';

$pdo = conexao();
$st = $pdo->prepare("UPDATE msoft.UsuarioGrupoTelas SET Tela = 'followup-pedidos' WHERE Tela = 'pedidos'");
$st->execute();
echo 'Linhas atualizadas: ' . $st->rowCount() . PHP_EOL;
