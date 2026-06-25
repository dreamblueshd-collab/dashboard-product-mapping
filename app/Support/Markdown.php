<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Throwable;

/**
 * Render Markdown -> HTML yang aman untuk menampilkan jawaban AI.
 * HTML mentah dari input di-escape (mencegah XSS), link tidak aman dibuang.
 */
class Markdown
{
    public static function toHtml(?string $markdown): string
    {
        $markdown = (string) $markdown;
        if (trim($markdown) === '') {
            return '';
        }

        try {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
                'renderer' => ['soft_break' => "<br>\n"],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);

            $converter = new MarkdownConverter($environment);

            return $converter->convert($markdown)->getContent();
        } catch (Throwable) {
            // Fallback aman: tampilkan teks polos.
            return nl2br(e($markdown));
        }
    }
}
