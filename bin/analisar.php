<?php

declare(strict_types=1);

require __DIR__ . '/../src/LeitorCsv.php';
require __DIR__ . '/../src/AnalisadorSod.php';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php bin/analisar.php acessos.csv regras.csv [saida.csv]\n");
    exit(1);
}

foreach ([$argv[1], $argv[2]] as $arquivo) {
    if (!is_file($arquivo)) {
        fwrite(STDERR, "Arquivo não encontrado: $arquivo\n");
        exit(1);
    }
}

$analisador = new AnalisadorSod();
$analisador->carregarRegras(LeitorCsv::ler((string) file_get_contents($argv[2])));
$conflitos = $analisador->analisar(LeitorCsv::ler((string) file_get_contents($argv[1])));

$lado = fn (array $l): string => implode(', ', array_keys($l));
$linhas = array_map(fn ($c) => [$c['usuario'], $c['risco'], $c['descricao'], $lado($c['lado_a']), $lado($c['lado_b'])], $conflitos);

if (isset($argv[3])) {
    file_put_contents($argv[3], LeitorCsv::gerar(['usuario', 'risco', 'descricao', 'lado_a', 'lado_b'], $linhas));
    echo count($conflitos) . " conflitos gravados em {$argv[3]}\n";
    exit(0);
}

foreach ($linhas as [$usuario, $risco, $descricao, $a, $b]) {
    printf("%-12s %-6s %s\n%19s A: %s | B: %s\n", $usuario, $risco, $descricao, '', $a, $b);
}
echo count($conflitos) . " conflitos\n";
