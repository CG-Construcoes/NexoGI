<div class="login-page">
    <div class="login-card">
        <div class="login-logo">Msoft</div>
        <?php if ($status !== null): ?>
        <p class="login-sub">Senha cadastrada</p>
        <div class="alert-ok">
            <?= $status === 'ativo'
                ? 'Senha definida com sucesso. Voce ja pode entrar no sistema.'
                : 'Senha cadastrada! Seu acesso sera liberado pelo administrador.' ?>
        </div>
        <a href="<?= url('login') ?>" class="btn btn-block" style="margin-top:16px">Ir para o login</a>
        <?php elseif (!$dados): ?>
        <p class="login-sub">Link invalido</p>
        <div class="alert-erro">Este link e invalido, ja foi usado ou expirou. Solicite um novo.</div>
        <a href="<?= url('acesso') ?>" class="btn btn-block" style="margin-top:16px">Solicitar novo link</a>
        <?php else: ?>
        <p class="login-sub"><?= $dados['Tipo'] === 'definir' ? 'Cadastre sua senha' : 'Redefina sua senha' ?></p>
        <?php if ($erro): ?>
        <div class="alert-erro" style="margin-bottom:6px"><?= e($erro) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= url('definir-senha') ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?php if ($dados['Tipo'] === 'definir'): ?>
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" required autofocus
                   autocomplete="name" maxlength="120" value="<?= e($nome !== '' ? $nome : '') ?>">
            <?php endif; ?>
            <label for="senha">Nova senha</label>
            <input type="password" id="senha" name="senha" required
                   <?= $dados['Tipo'] !== 'definir' ? 'autofocus' : '' ?> autocomplete="new-password">
            <label for="confirma">Confirmar senha</label>
            <input type="password" id="confirma" name="confirma" required autocomplete="new-password">
            <button type="submit" class="btn btn-block" style="margin-top:18px">Cadastrar senha</button>
        </form>
        <?php endif; ?>
    </div>
</div>
