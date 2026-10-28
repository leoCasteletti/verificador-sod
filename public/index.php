<?php

declare(strict_types=1);

require __DIR__ . '/../src/LeitorCsv.php';
require __DIR__ . '/../src/AnalisadorSod.php';

$exemplos = __DIR__ . '/../exemplos';

$lerArquivo = function (string $campo, string $exemplo) use ($exemplos): string {
    $arquivo = $_FILES[$campo] ?? null;
    if ($arquivo && $arquivo['error'] === UPLOAD_ERR_OK) {
        return (string) file_get_contents($arquivo['tmp_name']);
    }

    return isset($_POST['exemplo']) ? (string) file_get_contents("$exemplos/$exemplo") : '';
};

$conflitos = null;
$erro = '';
$usuarios = 0;
$analisador = new AnalisadorSod();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acessos = LeitorCsv::ler($lerArquivo('acessos', 'acessos.csv'));
    $analisador->carregarRegras(LeitorCsv::ler($lerArquivo('regras', 'regras.csv')));

    if ($acessos === [] || $analisador->totalRegras() === 0) {
        $erro = 'Envie os dois arquivos ou use os dados de exemplo.';
    } else {
        $conflitos = $analisador->analisar($acessos);
        $usuarios = count(array_unique(array_map(fn ($a) => mb_strtoupper($a['usuario'] ?? ''), $acessos)));
    }
}

$formatarLado = fn (array $lado): string => implode(', ', array_map(
    fn ($t, $funcoes) => "$t (" . implode(', ', $funcoes) . ')',
    array_keys($lado),
    $lado
));


$e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$comConflito = $conflitos ? count(array_unique(array_column($conflitos, 'usuario'))) : 0;
$csvConflitos = $conflitos ? base64_encode(LeitorCsv::gerar(
    ['usuario', 'risco', 'descricao', 'lado_a', 'lado_b'],
    array_map(fn ($c) => [$c['usuario'], $c['risco'], $c['descricao'], $formatarLado($c['lado_a']), $formatarLado($c['lado_b'])], $conflitos)
)) : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificador de SoD</title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<main class="container py-5">
    <header class="mb-4">
        <h1 class="h3">Verificador de SoD</h1>
        <p class="text-secondary mb-0">Cruza os acessos dos usuários com uma matriz de riscos de segregação de funções.</p>
    </header>

    <form method="post" enctype="multipart/form-data" class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="acessos" class="form-label">Acessos <small class="text-secondary">usuario;funcao;transacao</small></label>
                <input id="acessos" name="acessos" type="file" accept=".csv" class="form-control">
            </div>
            <div class="col-md-6">
                <label for="regras" class="form-label">Regras <small class="text-secondary">risco;descricao;lado_a;lado_b</small></label>
                <input id="regras" name="regras" type="file" accept=".csv" class="form-control">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn btn-dark">Analisar</button>
            <button type="submit" name="exemplo" value="1" class="btn btn-outline-secondary">Usar dados de exemplo</button>
        </div>
        <?php if ($erro): ?>
            <div class="alert alert-warning mt-3 mb-0"><?= $e($erro) ?></div>
        <?php endif; ?>
    </form>

    <?php if ($conflitos !== null): ?>
        <div class="row g-3 mb-4 resumo">
            <div class="col-6 col-md-3"><div class="card card-body"><span><?= $usuarios ?></span>usuários</div></div>
            <div class="col-6 col-md-3"><div class="card card-body"><span><?= $analisador->totalRegras() ?></span>regras</div></div>
            <div class="col-6 col-md-3"><div class="card card-body"><span><?= $comConflito ?></span>com conflito</div></div>
            <div class="col-6 col-md-3"><div class="card card-body"><span><?= count($conflitos) ?></span>conflitos</div></div>
        </div>

        <?php if ($conflitos === []): ?>
            <p class="text-success">Nenhum conflito encontrado.</p>
        <?php else: ?>
            <div class="d-flex justify-content-end mb-2">
                <a class="btn btn-outline-dark btn-sm" download="conflitos_sod.csv" href="data:text/csv;charset=utf-8;base64,<?= $csvConflitos ?>">Baixar CSV</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle conflitos">
                    <thead>
                    <tr><th>Usuário</th><th>Risco</th><th>Transações A</th><th>Transações B</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($conflitos as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= $e($c['usuario']) ?></td>
                            <td><span class="badge text-bg-danger"><?= $e($c['risco']) ?></span><br><small><?= $e($c['descricao']) ?></small></td>
                            <td><code><?= $e($formatarLado($c['lado_a'])) ?></code></td>
                            <td><code><?= $e($formatarLado($c['lado_b'])) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
