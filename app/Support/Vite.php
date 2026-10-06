<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Substituto mínimo do `@vite` (Laravel 9+) para Laravel 8: lê o manifest gerado por
 * `npm run build` (public/build/manifest.json) ou, em desenvolvimento, o arquivo `public/hot`
 * criado por `npm run dev`. O frontend continua sendo Vite + Tailwind + Alpine.
 */
class Vite
{
    /** @param string[] $entradas */
    public function tags(array $entradas): HtmlString
    {
        $hot = public_path('hot');
        if (is_file($hot)) {
            $base = rtrim(trim((string) file_get_contents($hot)), '/');
            $html = '<script type="module" src="'.e($base).'/@vite/client"></script>';
            foreach ($entradas as $e) {
                $html .= $this->tag($base.'/'.$e, $e);
            }

            return new HtmlString($html);
        }

        $arquivo = public_path('build/manifest.json');
        if (! is_file($arquivo)) {
            return new HtmlString('');
        }
        $manifest = json_decode((string) file_get_contents($arquivo), true) ?: [];
        $html = '';
        $vistos = [];
        foreach ($entradas as $e) {
            if (! isset($manifest[$e])) {
                continue;
            }
            $html .= $this->tagsDoChunk($manifest, $e, $vistos);
        }

        return new HtmlString($html);
    }

    private function tagsDoChunk(array $manifest, string $chave, array &$vistos): string
    {
        if (isset($vistos[$chave]) || ! isset($manifest[$chave])) {
            return '';
        }
        $vistos[$chave] = true;
        $chunk = $manifest[$chave];
        $html = '';
        foreach ($chunk['imports'] ?? [] as $import) {
            $html .= $this->tagsDoChunk($manifest, $import, $vistos);
        }
        foreach ($chunk['css'] ?? [] as $css) {
            $html .= $this->tag(asset('build/'.$css), $css);
        }
        $html .= $this->tag(asset('build/'.$chunk['file']), $chunk['file']);

        return $html;
    }

    private function tag(string $url, string $nome): string
    {
        if (preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)$/i', $nome)) {
            return '<link rel="stylesheet" href="'.e($url).'">';
        }

        return '<script type="module" src="'.e($url).'"></script>';
    }
}
