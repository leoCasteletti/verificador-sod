<?php

declare(strict_types=1);

final class AnalisadorSod
{
    /** @var array<string, array{descricao: string, lado_a: list<string>, lado_b: list<string>}> */
    private array $regras = [];

    /** @param list<array<string, string>> $registros colunas: risco, descricao, lado_a, lado_b */
    public function carregarRegras(array $registros): void
    {
        foreach ($registros as $r) {
            $id = $r['risco'] ?? '';
            if ($id === '') {
                continue;
            }
            $this->regras[$id] = [
                'descricao' => $r['descricao'] ?? '',
                'lado_a' => $this->listaTransacoes($r['lado_a'] ?? ''),
                'lado_b' => $this->listaTransacoes($r['lado_b'] ?? ''),
            ];
        }
    }

    public function totalRegras(): int
    {
        return count($this->regras);
    }

    /**
     * @param list<array<string, string>> $acessos colunas: usuario, transacao, funcao (opcional)
     * @return list<array{usuario: string, risco: string, descricao: string, lado_a: array<string, list<string>>, lado_b: array<string, list<string>>}>
     */
    public function analisar(array $acessos): array
    {
        $porUsuario = [];
        foreach ($acessos as $a) {
            $usuario = mb_strtoupper(trim($a['usuario'] ?? ''), 'UTF-8');
            $transacao = mb_strtoupper(trim($a['transacao'] ?? ''), 'UTF-8');
            if ($usuario === '' || $transacao === '') {
                continue;
            }
            $funcao = trim($a['funcao'] ?? '') ?: '(direto)';
            $porUsuario[$usuario][$transacao][$funcao] = true;
        }
        ksort($porUsuario);

        $conflitos = [];
        foreach ($porUsuario as $usuario => $transacoes) {
            foreach ($this->regras as $id => $regra) {
                $a = $this->origem($transacoes, $regra['lado_a']);
                $b = $this->origem($transacoes, $regra['lado_b']);
                if ($a !== [] && $b !== []) {
                    $conflitos[] = [
                        'usuario' => $usuario,
                        'risco' => $id,
                        'descricao' => $regra['descricao'],
                        'lado_a' => $a,
                        'lado_b' => $b,
                    ];
                }
            }
        }

        return $conflitos;
    }

    /**
     * @param array<string, array<string, true>> $transacoesDoUsuario
     * @param list<string> $lado
     * @return array<string, list<string>> transação => funções que a concedem
     */
    private function origem(array $transacoesDoUsuario, array $lado): array
    {
        $encontradas = [];
        foreach ($lado as $t) {
            if (isset($transacoesDoUsuario[$t])) {
                $encontradas[$t] = array_keys($transacoesDoUsuario[$t]);
            }
        }

        return $encontradas;
    }

    /** @return list<string> */
    private function listaTransacoes(string $texto): array
    {
        $itens = preg_split('/[|,\s]+/', mb_strtoupper($texto, 'UTF-8')) ?: [];

        return array_values(array_unique(array_filter($itens, fn ($t) => $t !== '')));
    }
}
