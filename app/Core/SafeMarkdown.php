<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class SafeMarkdown
{
    public static function render(string $markdown, array $variables = []): string
    {
        $markdown = self::interpolate($markdown, $variables);
        $lines = preg_split('/\R/', trim($markdown)) ?: [];
        return self::blocks($lines);
    }

    public static function interpolate(string $text, array $variables = []): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }
        return strtr($text, $replacements);
    }

    private static function blocks(array $lines): string
    {
        $html = [];
        $paragraph = [];
        $inList = false;
        $flushParagraph = static function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . self::inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $closeList = static function () use (&$html, &$inList): void {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (str_starts_with($line, '### ')) {
                $flushParagraph();
                $closeList();
                $html[] = '<h3>' . self::inline(substr($line, 4)) . '</h3>';
                continue;
            }
            if (str_starts_with($line, '## ')) {
                $flushParagraph();
                $closeList();
                $html[] = '<h2>' . self::inline(substr($line, 3)) . '</h2>';
                continue;
            }
            if (str_starts_with($line, '- ')) {
                $flushParagraph();
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . self::inline(substr($line, 2)) . '</li>';
                continue;
            }
            $closeList();
            $paragraph[] = $line;
        }
        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $links = [];
        $text = preg_replace_callback('/\[([^\]\r\n]{1,200})\]\(([^)\s]{1,1000})\)/', static function (array $match) use (&$links): string {
            $url = trim($match[2]);
            if (!str_starts_with($url, '/') && filter_var($url, FILTER_VALIDATE_URL) === false) {
                return $match[1];
            }
            if (!str_starts_with($url, '/') && !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                return $match[1];
            }
            $token = '@@CAM_LINK_' . count($links) . '@@';
            $rel = str_starts_with($url, '/') ? '' : ' rel="nofollow"';
            $links[$token] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
                . '"' . $rel . '>' . htmlspecialchars($match[1], ENT_QUOTES, 'UTF-8') . '</a>';
            return $token;
        }, $text) ?? $text;
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;

        return strtr($text, $links);
    }
}
