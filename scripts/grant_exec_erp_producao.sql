/*
 * Libera EXECUTE das procedures ERP para o login do app PHP (claudeai).
 * Rodar no SQL Server de producao como sa/admin (SSMS).
 * Cobre: Relatorio Economico/Financeiro, DRE ao vivo e Agenda de Pagamentos.
 */
USE dbMGA;
GO
GRANT EXECUTE ON dbo.spMovFinLancamentosDetalhe TO [claudeai];
GRANT EXECUTE ON dbo.spMovEconomicoDetalhe    TO [claudeai];
GRANT EXECUTE ON dbo.sp_ParcelasAgendaPagto   TO [claudeai];
GO

USE dbConv;
GO
GRANT EXECUTE ON dbo.spMovFinLancamentosDetalhe TO [claudeai];
GRANT EXECUTE ON dbo.spMovEconomicoDetalhe    TO [claudeai];
GRANT EXECUTE ON dbo.sp_ParcelasAgendaPagto   TO [claudeai];
GO
