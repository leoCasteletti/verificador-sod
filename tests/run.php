<?php

declare(strict_types=1);

require __DIR__ . '/../src/LeitorCsv.php';
require __DIR__ . '/../src/AnalisadorSod.php';

$falhas = 0;
$verificar = function (string $nome, bool $ok) use (&$falhas): void {
    if (!$ok) {
        $falhas++;
        echo "FALHOU: $nome\n";
    }
};

$analisador = new AnalisadorSod();
$analisador->carregarRegras([
    ['risco' => 'R1', 'descricao' => 'Compra x Entrada', 'lado_a' => 'ME21N|ME22N', 'lado_b' => 'MIGO'],
    ['risco' => 'R2', 'descricao' => 'Usuário x Função', 'lado_a' => 'su01', 'lado_b' => 'pfcg'],
]);

$conflitos = $analisador->analisar([
    ['usuario' => 'ana', 'funcao' => 'Z_COMPRAS', 'transacao' => 'me21n'],
    ['usuario' => 'ANA', 'funcao' => 'Z_ALMOX', 'transacao' => 'MIGO'],
    ['usuario' => 'BRUNO', 'funcao' => 'Z_COMPRAS', 'transacao' => 'ME21N'],
    ['usuario' => 'CARLA', 'funcao' => '', 'transacao' => 'SU01'],
    ['usuario' => 'CARLA', 'funcao' => 'Z_BASIS', 'transacao' => 'PFCG'],
]);

$verificar('dois conflitos', count($conflitos) === 2);
$verificar('ANA em R1', $conflitos[0]['usuario'] === 'ANA' && $conflitos[0]['risco'] === 'R1');
$verificar('origem por função', $conflitos[0]['lado_a'] === ['ME21N' => ['Z_COMPRAS']] && $conflitos[0]['lado_b'] === ['MIGO' => ['Z_ALMOX']]);
$verificar('acesso direto', $conflitos[1]['lado_a'] === ['SU01' => ['(direto)']]);
$verificar('BRUNO sem conflito', !in_array('BRUNO', array_column($conflitos, 'usuario'), true));

$csv = LeitorCsv::ler("usuario;funcao;transacao\r\nANA;Z_A;MIGO\r\n\r\n");
$verificar('leitura CSV', $csv === [['usuario' => 'ANA', 'funcao' => 'Z_A', 'transacao' => 'MIGO']]);

$ansi = mb_convert_encoding("risco,descricao\nR1,Lançar fatura\n", 'Windows-1252', 'UTF-8');
$verificar('CSV Windows-1252 com vírgula', LeitorCsv::ler($ansi)[0]['descricao'] === 'Lançar fatura');

$exemplo = new AnalisadorSod();
$exemplo->carregarRegras(LeitorCsv::ler((string) file_get_contents(__DIR__ . '/../exemplos/regras.csv')));
$achados = $exemplo->analisar(LeitorCsv::ler((string) file_get_contents(__DIR__ . '/../exemplos/acessos.csv')));
$verificar('dados de exemplo', count($achados) === 4);

echo (8 - $falhas) . "/8 testes passaram\n";
exit($falhas > 0 ? 1 : 0);
