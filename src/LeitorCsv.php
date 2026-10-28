<?php

declare(strict_types=1);

final class LeitorCsv
{
    /** @return list<array<string, string>> */
    public static function ler(string $conteudo): array
    {
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        $linhas = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($conteudo)) ?: [],
            fn ($l) => trim($l) !== ''
        ));
        if ($linhas === []) {
            return [];
        }

        $separador = substr_count($linhas[0], ';') >= substr_count($linhas[0], ',') ? ';' : ',';
        $cabecalho = array_map(
            fn ($c) => mb_strtolower(trim($c), 'UTF-8'),
            str_getcsv(array_shift($linhas), $separador, '"', '')
        );

        $registros = [];
        foreach ($linhas as $linha) {
            $campos = array_map('trim', str_getcsv($linha, $separador, '"', ''));
            $campos = array_pad(array_slice($campos, 0, count($cabecalho)), count($cabecalho), '');
            $registros[] = array_combine($cabecalho, $campos);
        }

        return $registros;
    }

    /** @param list<string> $cabecalho @param list<list<string>> $linhas */
    public static function gerar(array $cabecalho, array $linhas): string
    {
        $saida = fopen('php://temp', 'r+');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, $cabecalho, ';', '"', '');
        foreach ($linhas as $linha) {
            fputcsv($saida, $linha, ';', '"', '');
        }
        rewind($saida);

        return (string) stream_get_contents($saida);
    }
}
