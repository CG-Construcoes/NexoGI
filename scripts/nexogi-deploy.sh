#!/bin/bash
# Deploy do NexoGI — roda via cron do usuario suporte a cada 5 min.
# Fica FORA do repo para nunca sujar o working tree (qualquer arquivo
# modificado trava o git pull --ff-only). Copia versionada em
# scripts/nexogi-deploy.sh.
cd /home/suporte/Documents/NexoGI || exit 1
date "+%F %T"

# A conexao com o GitHub falha as vezes (o log tinha timeouts de ~135s por
# tentativa). lowSpeed* aborta uma conexao travada em ~20s e tentamos ate
# 3x na mesma rodada — alem do retry natural do cron a cada 5 min.
out=""
ok=0
for i in 1 2 3; do
    out=$(git -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=20 pull --ff-only 2>&1) && { ok=1; break; }
    sleep 10
done
echo "$out"
[ "$ok" -eq 0 ] && echo "git pull FALHOU (tentativa $i)"

# Pull travado por alteracao local nao commitada: ja aconteceu com
# app/fluxo_caixa_saldos.php e o site ficou desatualizado em silencio.
# Agora avisa o ADMIN_EMAIL (1x por dia por episodio, flag em storage/)
# ate alguem commitar/descartar a alteracao local.
if [ "$ok" -eq 0 ] && echo "$out" | grep -q "would be overwritten"; then
    flag=storage/deploy-bloqueado.flag
    if [ ! -f "$flag" ] || [ -n "$(find "$flag" -mmin +1440 -print -quit 2>/dev/null)" ]; then
        { echo "$out"; echo; git status --porcelain -uno; } > "$flag"
        php -r '
            require "app/bootstrap.php";
            $detalhe = htmlspecialchars((string) file_get_contents("storage/deploy-bloqueado.flag"));
            $corpo = "<p>O <code>git pull</code> automatico do NexoGI (a cada 5 min) esta <b>bloqueado</b> por alteracoes locais nao commitadas no servidor:</p>"
                   . "<pre>" . $detalhe . "</pre>"
                   . "<p>Commite as alteracoes na branch <code>Misrain/meu-ambiente</code> ou descarte-as para o deploy voltar. Enquanto isso o site fica desatualizado.</p>";
            $okMail = enviar_email(env("ADMIN_EMAIL"), "[NexoGI] Deploy travado: alteracoes locais sem commit", $corpo);
            fwrite(STDERR, $okMail ? "aviso enviado a ADMIN_EMAIL\n" : "FALHA ao enviar aviso (ver error_log)\n");
        ' 2>&1 || true
    fi
fi

# Deploy ok — limpa o flag para que o proximo bloqueio avise de novo.
[ "$ok" -eq 1 ] && rm -f storage/deploy-bloqueado.flag

[ "$ok" -eq 0 ] && exit 1

# bootstrap.php exige vendor/autoload.php — sem ele o site morre (500).
# Reinstala quando falta vendor/ ou quando composer.lock e mais novo
# que o ultimo install. ext-mbstring e ignorada: o servidor nao tem a
# extensao e o app usa polyfill (so laminas-escaper a exige, e nao e
# usado pelo codigo).
if [ -f composer.json ] && {
    [ ! -f vendor/autoload.php ] ||
    [ ! -f vendor/composer/installed.json ] ||
    [ composer.lock -nt vendor/composer/installed.json ]
}; then
    /home/suporte/bin/composer install --no-dev --no-interaction \
        --ignore-platform-req=ext-mbstring
fi
