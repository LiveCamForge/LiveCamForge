<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use LiveCamForge\Core\Config;
use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\TrafficLanding;
use LiveCamForge\Core\Translator;
use PDO;
use RuntimeException;

final class TrafficLandingRepository
{
    private const AGE_VALUES = ['', '18-20', '21-25', '26-30', '31-35', '36-40', '41-plus'];
    private const STATUS_VALUES = ['', 'public', 'private', 'group', 'away', 'unknown'];
    private const SORT_VALUES = ['popular', 'provider_popular', 'newest', 'youngest', 'oldest', 'name'];

    private array $locales;

    public function __construct(private PDO $pdo, private Config $config, array $locales)
    {
        $this->locales = array_values(array_unique(array_filter(
            array_map(static fn (mixed $locale): string => (string) $locale, $locales),
            static fn (string $locale): bool => preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) === 1
        )));
        if ($this->locales === []) {
            throw new RuntimeException('At least one valid language pack is required for traffic landings.');
        }
    }

    public function enabled(Translator $translator): array
    {
        return TrafficLanding::enabledDefinitions($this->records(), $translator);
    }

    public function records(): array
    {
        $records = $this->definitions();
        uasort($records, static fn (array $a, array $b): int =>
            [$a['sort_order'] ?? 100, $a['slug']] <=> [$b['sort_order'] ?? 100, $b['slug']]
        );

        return $records;
    }

    public function find(string $slug): ?array
    {
        return $this->records()[$slug] ?? null;
    }

    public function save(array $input, array $enabledProviders): string
    {
        $originalSlug = strtolower(trim((string) ($input['original_slug'] ?? '')));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,60}$/', $slug) !== 1) {
            throw new RuntimeException('Invalid landing slug.');
        }

        $standardDefinitions = $this->standardDefinitions();
        $isStandard = isset($standardDefinitions[$originalSlug !== '' ? $originalSlug : $slug]);
        if ($isStandard) {
            $slug = $originalSlug !== '' ? $originalSlug : $slug;
        } elseif ($originalSlug !== '' && $originalSlug !== $slug) {
            throw new RuntimeException('Published slugs cannot be renamed. Create a new landing instead.');
        }

        if (!$isStandard && $originalSlug === '' && $this->customCount() >= 50) {
            throw new RuntimeException('The maximum of 50 custom landings has been reached.');
        }
        if (!$isStandard && $originalSlug === '' && $this->find($slug) !== null) {
            throw new RuntimeException('This landing slug already exists.');
        }

        $provider = strtolower(trim((string) ($input['filter_provider'] ?? '')));
        if ($provider !== '' && !in_array($provider, $enabledProviders, true)) {
            throw new RuntimeException('Invalid landing provider.');
        }
        $gender = in_array($input['filter_gender'] ?? '', ['', 'f', 'm', 't', 'c'], true)
            ? (string) ($input['filter_gender'] ?? '') : '';
        $countryInput = trim((string) ($input['filter_country'] ?? ''));
        $country = $countryInput === '' ? '' : (PerformerCountry::normalize($countryInput) ?? '');
        if ($countryInput !== '' && $country === '') {
            throw new RuntimeException('Invalid landing country.');
        }
        $age = in_array($input['filter_age'] ?? '', self::AGE_VALUES, true)
            ? (string) ($input['filter_age'] ?? '') : '';
        $status = in_array($input['filter_room_status'] ?? '', self::STATUS_VALUES, true)
            ? (string) ($input['filter_room_status'] ?? '') : '';
        $sort = in_array($input['filter_sort'] ?? '', self::SORT_VALUES, true)
            ? (string) ($input['filter_sort'] ?? 'popular') : 'popular';
        $tag = strtolower(ltrim(trim((string) ($input['filter_tag'] ?? '')), '#'));
        if ($tag !== '' && preg_match('/^[a-z0-9_-]{1,50}$/', $tag) !== 1) {
            throw new RuntimeException('Invalid landing tag.');
        }

        $filters = array_filter([
            'provider' => $provider,
            'gender' => $gender,
            'country' => $country,
            'tag' => $tag,
            'age' => $age,
            'room_status' => $status,
            'new_only' => isset($input['filter_new_only']) ? true : null,
            'new_days' => isset($input['filter_new_only'])
                ? max(1, min(90, (int) ($input['filter_new_days'] ?? 7))) : null,
            'sort' => $sort,
        ], static fn (mixed $value): bool => $value !== '' && $value !== null);

        $existing = $this->find($slug);
        $content = is_array($existing['content'] ?? null) ? $existing['content'] : [];
        foreach ($this->locales as $locale) {
            $content[$locale] = [
                'title' => self::text($input['title_' . $locale] ?? '', 160),
                'heading' => self::text($input['heading_' . $locale] ?? '', 180),
                'description' => self::text($input['description_' . $locale] ?? '', 320),
                'eyebrow' => self::text($input['eyebrow_' . $locale] ?? '', 100),
                'intro' => self::text($input['intro_' . $locale] ?? '', 3000),
                'body' => self::text($input['body_' . $locale] ?? '', 30000),
                'faq' => $this->faq($input, $locale),
            ];
            foreach (['title', 'heading', 'description', 'eyebrow'] as $staticField) {
                if (preg_match('/\{(?:site_name|result_count|provider_name|landing_title)\}/', $content[$locale][$staticField]) === 1) {
                    throw new RuntimeException('Dynamic placeholders are allowed only in introduction, body and FAQ fields.');
                }
            }
        }

        $indexable = isset($input['indexable']);
        $enabled = isset($input['enabled']);
        $siteLocale = (string) $this->config->get('locale', 'en');
        $fallbackLocale = (string) $this->config->get('fallback_locale', 'en');
        $firstContent = reset($content);
        $siteContent = $content[$siteLocale]
            ?? $content[$fallbackLocale]
            ?? $content['en']
            ?? (is_array($firstContent) ? $firstContent : []);
        if ($enabled && $siteContent['title'] === '') {
            throw new RuntimeException('Published landings require a title in the site language.');
        }
        if ($indexable && ($siteContent['title'] === '' || $siteContent['description'] === '' || $siteContent['intro'] === '')) {
            throw new RuntimeException('Indexable landings require title, meta description and introduction in the site language.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO traffic_landings '
            . '(slug, is_standard, enabled, indexable, show_in_navigation, minimum_results, sort_order, filters_json, content_json) '
            . 'VALUES (:slug, :is_standard, :enabled, :indexable, :show_in_navigation, :minimum_results, :sort_order, :filters_json, :content_json) '
            . 'ON DUPLICATE KEY UPDATE is_standard=VALUES(is_standard), enabled=VALUES(enabled), '
            . 'indexable=VALUES(indexable), show_in_navigation=VALUES(show_in_navigation), '
            . 'minimum_results=VALUES(minimum_results), sort_order=VALUES(sort_order), '
            . 'filters_json=VALUES(filters_json), content_json=VALUES(content_json)'
        );
        $statement->execute([
            'slug' => $slug,
            'is_standard' => $isStandard ? 1 : 0,
            'enabled' => $enabled ? 1 : 0,
            'indexable' => $indexable ? 1 : 0,
            'show_in_navigation' => isset($input['show_in_navigation']) ? 1 : 0,
            'minimum_results' => max(0, min(500, (int) ($input['minimum_results'] ?? 8))),
            'sort_order' => max(0, min(999, (int) ($input['sort_order'] ?? 100))),
            'filters_json' => json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'content_json' => json_encode(
                $content,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]);

        return $slug;
    }

    public function delete(string $slug): void
    {
        if (isset($this->standardDefinitions()[$slug])) {
            throw new RuntimeException('Standard landings cannot be deleted.');
        }
        $statement = $this->pdo->prepare('DELETE FROM traffic_landings WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
    }

    public function reset(string $slug): void
    {
        if (!isset($this->standardDefinitions()[$slug])) {
            throw new RuntimeException('Only standard landings can be reset.');
        }
        $statement = $this->pdo->prepare('DELETE FROM traffic_landings WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
    }

    private function definitions(): array
    {
        $definitions = $this->standardDefinitions();
        $rows = $this->pdo->query('SELECT * FROM traffic_landings ORDER BY sort_order, slug')->fetchAll();
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $base = $definitions[$slug] ?? ['slug' => $slug, 'is_standard' => false];
            $isStandard = (bool) ($base['is_standard'] ?? false);
            $content = json_decode((string) $row['content_json'], true);
            $filters = json_decode((string) $row['filters_json'], true);
            $definitions[$slug] = array_replace($base, [
                'slug' => $slug,
                'is_standard' => $isStandard,
                'overridden' => true,
                'enabled' => (bool) $row['enabled'],
                'index' => (bool) $row['indexable'],
                'show_in_navigation' => (bool) $row['show_in_navigation'],
                'minimum_results' => (int) $row['minimum_results'],
                'sort_order' => (int) $row['sort_order'],
                'filters' => is_array($filters) ? $filters : [],
                'content' => is_array($content) ? $content : [],
                'updated_at' => (string) $row['updated_at'],
            ]);
        }

        return $definitions;
    }

    private function standardDefinitions(): array
    {
        $configured = $this->config->get('traffic.landings', []);
        if (!is_array($configured)) {
            return [];
        }
        $definitions = [];
        $order = 10;
        foreach ($configured as $slug => $definition) {
            if (!is_string($slug) || !is_array($definition)) {
                continue;
            }
            $content = [];
            foreach ($this->locales as $locale) {
                $content[$locale] = [
                    'title' => $this->localized($definition['title'] ?? '', $locale),
                    'heading' => $this->localized($definition['heading'] ?? ($definition['title'] ?? ''), $locale),
                    'description' => $this->localized($definition['description'] ?? '', $locale),
                    'eyebrow' => $this->localized($definition['eyebrow'] ?? '', $locale),
                    'intro' => $this->localized($definition['intro'] ?? '', $locale),
                    'body' => $this->localized($definition['body'] ?? '', $locale),
                    'faq' => $this->localizedFaq($definition['faq'] ?? [], $locale),
                ];
            }
            $definitions[$slug] = [
                'slug' => $slug,
                'is_standard' => true,
                'overridden' => false,
                'enabled' => (bool) ($definition['enabled'] ?? false),
                'index' => (bool) ($definition['index'] ?? true),
                'show_in_navigation' => (bool) ($definition['show_in_navigation'] ?? true),
                'minimum_results' => (int) ($definition['minimum_results'] ?? 1),
                'sort_order' => (int) ($definition['sort_order'] ?? $order),
                'filters' => is_array($definition['filters'] ?? null) ? $definition['filters'] : [],
                'content' => $content,
                'updated_at' => null,
            ];
            $order += 10;
        }

        return $definitions;
    }

    private function customCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM traffic_landings WHERE is_standard = 0')->fetchColumn();
    }

    private function faq(array $input, string $locale): array
    {
        $faq = [];
        for ($index = 0; $index < 5; $index++) {
            $question = self::text($input['faq_question_' . $locale][$index] ?? '', 300);
            $answer = self::text($input['faq_answer_' . $locale][$index] ?? '', 2000);
            if ($question !== '' && $answer !== '') {
                $faq[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $faq;
    }

    private static function text(mixed $value, int $maximumLength): string
    {
        $value = trim((string) $value);
        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, $maximumLength, 'UTF-8'));
        }
        $value = substr($value, 0, $maximumLength);
        while ($value !== '' && preg_match('//u', $value) !== 1) {
            $value = substr($value, 0, -1);
        }
        return trim($value);
    }

    private function localized(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return trim((string) ($value[$locale] ?? ''));
        }
        return trim((string) $value);
    }

    private function localizedFaq(mixed $value, string $locale): array
    {
        if (is_array($value)) {
            $first = reset($value);
            if (array_key_exists($locale, $value) && is_array($value[$locale])) {
                $value = $value[$locale];
            } elseif (is_array($first) && !isset($first['question'], $first['answer'])) {
                $value = [];
            }
        }
        $faq = [];
        foreach (is_array($value) ? $value : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = $this->localized($item['question'] ?? '', $locale);
            $answer = $this->localized($item['answer'] ?? '', $locale);
            if ($question !== '' && $answer !== '') {
                $faq[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }
        return $faq;
    }
}
