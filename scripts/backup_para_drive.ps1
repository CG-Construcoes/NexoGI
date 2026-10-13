# Copia o projeto PHP do Ubuntu (WSL) para o Google Drive.
# Uso no Windows (PowerShell):
#   powershell -ExecutionPolicy Bypass -File "\\wsl.localhost\Ubuntu-26.04\var\www\msoft\scripts\backup_para_drive.ps1"
# O destino e um espelho: arquivos apagados no projeto tambem sao apagados no destino.
$origem  = '\\wsl.localhost\Ubuntu-26.04\var\www\msoft'
$destino = 'H:\Meu Drive\Msoft-PHP'

if (-not (Test-Path $origem)) {
    Write-Host "Projeto nao encontrado em $origem. O Ubuntu (WSL) esta instalado?" -ForegroundColor Red
    exit 1
}
if (-not (Test-Path 'H:\')) {
    Write-Host 'Disco H: (Google Drive) nao encontrado.' -ForegroundColor Red
    exit 1
}

robocopy $origem $destino /MIR /R:2 /W:2 /NFL /NDL /NP /XD .git | Out-Host
if ($LASTEXITCODE -ge 8) {
    Write-Host "Backup FALHOU (codigo $LASTEXITCODE)." -ForegroundColor Red
    exit 1
}
Write-Host "Backup concluido em $destino ($(Get-Date -Format 'dd/MM/yyyy HH:mm:ss'))." -ForegroundColor Green
