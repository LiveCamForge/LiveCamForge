<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class TrafficLanding
{
    /** @return array<string,array<string,mixed>> */
    public static function enabled(Config $config, Translator $translator): array
    {
        $definitions = $config->get('traffic.landings', []);
        return self::enabledDefinitions(is_array($definitions) ? $definitions : [], $translator);
    }

    /** @return array<string,array<string,mixed>> */
    public static function enabledDefinitions(array $definitions, Translator $translator): array
    {
        $enabled = [];
        foreach ($definitions as $slug => $definition) {
            if (!is_string($slug) || preg_match('/^[a-z0-9][a-z0-9-]{0,60}$/', $slug) !== 1
                || !is_array($definition) || !($definition['enabled'] ?? false)) {
                continue;
            }
            $enabled[$slug] = self::normalize($slug, $definition, $translator);
        }
        return $enabled;
    }

    /** @return array<string,mixed>|null */
    public static function find(Config $config, Translator $translator, string $slug): ?array
    {
        $landings = self::enabled($config, $translator);
        return $landings[$slug] ?? null;
    }

    /** @return array<string,mixed> */
    private static function normalize(string $slug, array $definition, Translator $translator): array
    {
        $filters = is_array($definition['filters'] ?? null) ? $definition['filters'] : [];
        $allowedFilters = ['provider', 'gender', 'country', 'tag', 'age', 'room_status', 'new_only', 'new_days', 'sort'];
        $filters = array_intersect_key($filters, array_flip($allowedFilters));
        $faqSource = $definition['faq'] ?? [];
        if (is_array($definition['content'] ?? null)) {
            $contentDefinitions = $definition['content'];
            $firstContent = reset($contentDefinitions);
            $localeContent = $contentDefinitions[$translator->locale()]
                ?? $contentDefinitions[$translator->fallbackLocale()]
                ?? $contentDefinitions['en']
                ?? $firstContent;
            if (is_array($localeContent)) {
                $faqSource = $localeContent['faq'] ?? $faqSource;
            }
        }
        $faq = [];
        foreach (is_array($faqSource) ? $faqSource : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = $item['question'] ?? '';
            $answer = $item['answer'] ?? '';
            if (is_array($question)) {
                $question = $question[$translator->locale()]
                    ?? $question[$translator->fallbackLocale()]
                    ?? $question['en']
                    ?? reset($question)
                    ?? '';
            }
            if (is_array($answer)) {
                $answer = $answer[$translator->locale()]
                    ?? $answer[$translator->fallbackLocale()]
                    ?? $answer['en']
                    ?? reset($answer)
                    ?? '';
            }
            $question = trim((string) $question);
            $answer = trim((string) $answer);
            if ($question !== '' && $answer !== '') {
                $faq[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return [
            'slug' => $slug,
            'title' => self::content($definition, 'title', $translator, ucwords(str_replace('-', ' ', $slug))),
            'heading' => self::content($definition, 'heading', $translator, self::content($definition, 'title', $translator, ucwords(str_replace('-', ' ', $slug)))),
            'description' => self::content($definition, 'description', $translator),
            'eyebrow' => self::content($definition, 'eyebrow', $translator, $translator->get('traffic.eyebrow')),
            'intro' => self::content($definition, 'intro', $translator),
            'body' => self::content($definition, 'body', $translator),
            'index' => (bool) ($definition['index'] ?? true),
            'show_in_navigation' => (bool) ($definition['show_in_navigation'] ?? true),
            'minimum_results' => max(0, min(500, (int) ($definition['minimum_results'] ?? 1))),
            'filters' => $filters,
            'faq' => $faq,
            'updated_at' => isset($definition['updated_at']) && is_string($definition['updated_at'])
                ? $definition['updated_at'] : null,
        ];
    }

    private static function content(array $definition, string $field, Translator $translator, string $fallback = ''): string
    {
        if (is_array($definition['content'] ?? null)) {
            $contentDefinitions = $definition['content'];
            $firstContent = reset($contentDefinitions);
            $localeContent = $contentDefinitions[$translator->locale()]
                ?? $contentDefinitions[$translator->fallbackLocale()]
                ?? $contentDefinitions['en']
                ?? $firstContent;
            if (is_array($localeContent) && array_key_exists($field, $localeContent)) {
                return trim((string) $localeContent[$field]);
            }
        }
        $value = $definition[$field] ?? $fallback;
        if (is_array($value)) {
            $value = ($value[$translator->locale()]
                ?? $value[$translator->fallbackLocale()]
                ?? $value['en']
                ?? reset($value)) ?: $fallback;
        }
        return trim((string) $value);
    }
}
