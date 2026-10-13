#!/usr/bin/env bash
# Verifica a sintaxe de todos os arquivos PHP do projeto. Uso: bash scripts/verificar_sintaxe.sh
cd "$(dirname "$0")/.." || exit 1
erros=0
while IFS= read -r arquivo; do
    if ! saida=$(php -l "$arquivo" 2>&1); then
        echo "$saida"
        erros=$((erros + 1))
    fi
done < <(find app views public scripts -name '*.php' | sort)
echo "Arquivos com erro: $erros"
exit "$erros"
