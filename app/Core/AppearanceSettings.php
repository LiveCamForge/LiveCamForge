<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

use LiveCamForge\Repositories\SettingsRepository;
use InvalidArgumentException;

final class AppearanceSettings
{
    private const COLORS = [
        'primary' => '#ff3d83',
        'accent' => '#7757ff',
        'background' => '#0b0b12',
        'surface' => '#151520',
        'text' => '#f5f5fa',
        'muted' => '#a5a5b8',
    ];

    private const PRESETS = [
        'livecamforge' => [
            'colors' => [
                'primary' => '#ff3d83',
                'accent' => '#7757ff',
                'background' => '#0b0b12',
                'surface' => '#151520',
                'text' => '#f5f5fa',
                'muted' => '#a5a5b8',
            ],
            'font' => 'system',
        ],
        'midnight' => [
            'colors' => [
                'primary' => '#5b8cff',
                'accent' => '#8b5cf6',
                'background' => '#080d1b',
                'surface' => '#111a2e',
                'text' => '#f1f5ff',
                'muted' => '#94a3c7',
            ],
            'font' => 'system',
        ],
        'neon' => [
            'colors' => [
                'primary' => '#ff2bd6',
                'accent' => '#00e5ff',
                'background' => '#08070d',
                'surface' => '#15111f',
                'text' => '#f8f5ff',
                'muted' => '#aaa0bb',
            ],
            'font' => 'modern',
        ],
        'velvet' => [
            'colors' => [
                'primary' => '#e94b78',
                'accent' => '#a85578',
                'background' => '#11070b',
                'surface' => '#211017',
                'text' => '#fff3f6',
                'muted' => '#c4a0aa',
            ],
            'font' => 'serif',
        ],
        'ocean' => [
            'colors' => [
                'primary' => '#28b8d8',
                'accent' => '#4f7cff',
                'background' => '#07131b',
                'surface' => '#0d2430',
                'text' => '#effbff',
                'muted' => '#8fb2c0',
            ],
            'font' => 'system',
        ],
        'crimson' => [
            'colors' => [
                'primary' => '#ef3340',
                'accent' => '#ff5a5f',
                'background' => '#0b090a',
                'surface' => '#1b1416',
                'text' => '#fff5f5',
                'muted' => '#bca3a6',
            ],
            'font' => 'system',
        ],
        'luxury_gold' => [
            'colors' => [
                'primary' => '#d4af37',
                'accent' => '#e0c36e',
                'background' => '#0d0b08',
                'surface' => '#1d1912',
                'text' => '#fff9e8',
                'muted' => '#b9aa86',
            ],
            'font' => 'serif',
        ],
        'clean_light' => [
            'colors' => [
                'primary' => '#df3f78',
                'accent' => '#7255d9',
                'background' => '#f5f6fa',
                'surface' => '#ffffff',
                'text' => '#1b1b24',
                'muted' => '#69707d',
            ],
            'font' => 'modern',
        ],
    ];

    private const CARD_STYLES = [
        'default' => [
            'radius' => '15px',
            'gap' => '18px',
            'columns' => 'repeat(4,1fr)',
            'body_padding' => '15px',
            'title_size' => '18px',
            'border' => 'var(--border)',
            'background' => 'var(--panel)',
            'shadow' => 'none',
            'image_ratio' => '4 / 3',
            'hover_lift' => '-3px',
        ],
        'rounded' => [
            'radius' => '24px',
            'gap' => '20px',
            'columns' => 'repeat(4,1fr)',
            'body_padding' => '17px',
            'title_size' => '18px',
            'border' => 'color-mix(in srgb,var(--purple) 22%,var(--border))',
            'background' => 'var(--panel)',
            'shadow' => '0 10px 28px color-mix(in srgb,var(--bg) 72%,transparent)',
            'image_ratio' => '4 / 3',
            'hover_lift' => '-4px',
        ],
        'compact' => [
            'radius' => '11px',
            'gap' => '12px',
            'columns' => 'repeat(5,1fr)',
            'body_padding' => '10px',
            'title_size' => '15px',
            'border' => 'var(--border)',
            'background' => 'var(--panel)',
            'shadow' => 'none',
            'image_ratio' => '1 / 1',
            'hover_lift' => '-2px',
        ],
        'minimal' => [
            'radius' => '8px',
            'gap' => '18px',
            'columns' => 'repeat(4,1fr)',
            'body_padding' => '12px 4px 6px',
            'title_size' => '17px',
            'border' => 'transparent',
            'background' => 'transparent',
            'shadow' => 'none',
            'image_ratio' => '4 / 3',
            'hover_lift' => '-2px',
        ],
    ];

    private const FONTS = [
        'system' => 'Inter,system-ui,-apple-system,Segoe UI,sans-serif',
        'modern' => 'Arial,Helvetica,sans-serif',
        'rounded' => 'Trebuchet MS,Arial,sans-serif',
        'serif' => 'Georgia,Times New Roman,serif',
    ];

    public function __construct(
        private SettingsRepository $settings,
        private Config $config,
        private Translator $translator
    ) {
    }

    public function values(): array
    {
        $locale = $this->translator->locale();
        $font = $this->value('appearance.font', 'system');
        if (!array_key_exists($font, self::FONTS)) {
            $font = 'system';
        }

        $colors = [];
        foreach (self::COLORS as $name => $default) {
            $stored = strtolower($this->value('appearance.color.' . $name, $default));
            $colors[$name] = self::isColor($stored) ? $stored : $default;
        }

        $cardStyle = $this->value('appearance.card_style', 'default');
        if (!array_key_exists($cardStyle, self::CARD_STYLES)) {
            $cardStyle = 'default';
        }

        return [
            'site_name' => $this->boundedValue('site.name', (string) $this->config->get('name', 'LiveCamForge'), 80),
            'logo_file' => $this->safeFilename($this->settings->get('branding.logo_file')),
            'favicon_file' => $this->safeFilename($this->settings->get('branding.favicon_file')),
            'colors' => $colors,
            'font' => $font,
            'preset' => self::detectPreset($colors, $font),
            'font_stack' => self::FONTS[$font],
            'card_style' => $cardStyle,
            'card_style_values' => self::CARD_STYLES[$cardStyle],
            'show_hero' => $this->value('appearance.show_hero', '1') !== '0',
            'hero_eyebrow' => $this->localizedBoundedValue('hero_eyebrow', $this->translator->get('home.eyebrow'), 120),
            'hero_title' => $this->localizedBoundedValue('hero_title', $this->translator->get('home.title'), 180),
            'hero_intro' => $this->localizedBoundedValue('hero_intro', $this->translator->get('home.intro'), 400),
            'footer_text' => $this->localizedBoundedValue('footer_text', '', 240),
            'localized_content' => $this->localizedContent(),
            'locale' => $locale,
        ];
    }

    /** @return array<string, array<string, string>> */
    private function localizedContent(): array
    {
        $content = [];
        foreach ($this->translator->available() as $locale => $_language) {
            $content[(string) $locale] = [
                'hero_eyebrow' => $this->exactBoundedValue('content.' . $locale . '.hero_eyebrow', 120),
                'hero_title' => $this->exactBoundedValue('content.' . $locale . '.hero_title', 180),
                'hero_intro' => $this->exactBoundedValue('content.' . $locale . '.hero_intro', 400),
                'footer_text' => $this->exactBoundedValue('content.' . $locale . '.footer_text', 240),
            ];
        }
        return $content;
    }

    public function save(array $input): void
    {
        $siteName = self::requiredText($input['site_name'] ?? '', 80);
        $font = (string) ($input['font'] ?? 'system');
        if (!array_key_exists($font, self::FONTS)) {
            throw new InvalidArgumentException('font');
        }

        $cardStyle = (string) ($input['card_style'] ?? 'default');
        if (!array_key_exists($cardStyle, self::CARD_STYLES)) {
            throw new InvalidArgumentException('card_style');
        }

        $values = [
            'site.name' => $siteName,
            'appearance.font' => $font,
            'appearance.card_style' => $cardStyle,
            'appearance.show_hero' => isset($input['show_hero']) ? '1' : '0',
        ];

        foreach (self::COLORS as $name => $default) {
            $color = strtolower(trim((string) ($input['color_' . $name] ?? $default)));
            if (!self::isColor($color)) {
                throw new InvalidArgumentException('color_' . $name);
            }
            $values['appearance.color.' . $name] = $color;
        }

        foreach ($this->translator->available() as $locale => $_language) {
            foreach ([
                'hero_eyebrow' => 120,
                'hero_title' => 180,
                'hero_intro' => 400,
                'footer_text' => 240,
            ] as $field => $maximum) {
                $key = $field . '_' . $locale;
                if (array_key_exists($key, $input)) {
                    $submitted = $input[$key];
                } elseif ($locale === $this->translator->locale() && array_key_exists($field, $input)) {
                    // Backward compatibility with the pre-0.24.10 form.
                    $submitted = $input[$field];
                } else {
                    continue;
                }
                $text = self::optionalText($submitted, $maximum);
                $values['content.' . $locale . '.' . $field] = $text === '' ? null : $text;
            }
        }

        $this->settings->setMany($values);
    }

    public function reset(): void
    {
        $values = [
            'site.name' => null,
            'appearance.font' => null,
            'appearance.card_style' => null,
            'appearance.show_hero' => null,
        ];
        foreach (array_keys(self::COLORS) as $name) {
            $values['appearance.color.' . $name] = null;
        }
        foreach ($this->translator->available() as $locale => $_language) {
            foreach (['hero_eyebrow', 'hero_title', 'hero_intro', 'footer_text'] as $field) {
                $values['content.' . $locale . '.' . $field] = null;
            }
        }
        $this->settings->setMany($values);
    }

    public static function fontNames(): array
    {
        return array_keys(self::FONTS);
    }

    public static function themePresets(): array
    {
        return self::PRESETS;
    }

    public static function cardStyles(): array
    {
        return self::CARD_STYLES;
    }

    public static function detectPreset(array $colors, string $font): string
    {
        foreach (self::PRESETS as $name => $preset) {
            if ($font === $preset['font'] && $colors === $preset['colors']) {
                return $name;
            }
        }
        return 'custom';
    }

    private function value(string $key, string $default): string
    {
        $value = $this->settings->get($key);
        return $value === null ? $default : $value;
    }

    private function localizedBoundedValue(string $field, string $default, int $maximum): string
    {
        foreach (array_values(array_unique([$this->translator->locale(), $this->translator->fallbackLocale(), 'en'])) as $locale) {
            $value = $this->settings->get('content.' . $locale . '.' . $field);
            if ($value !== null && $value !== '' && strlen($value) <= $maximum) {
                return $value;
            }
        }
        return $default;
    }

    private function exactBoundedValue(string $key, int $maximum): string
    {
        $value = $this->settings->get($key);
        return $value !== null && strlen($value) <= $maximum ? $value : '';
    }

    private function boundedValue(string $key, string $default, int $maximum): string
    {
        $value = $this->settings->get($key);
        if ($value === null || $value === '' || strlen($value) > $maximum) {
            return $default;
        }
        return $value;
    }

    private function safeFilename(?string $filename): ?string
    {
        return is_string($filename) && $filename !== '' && basename($filename) === $filename
            ? $filename
            : null;
    }

    private static function isColor(string $color): bool
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1;
    }

    private static function requiredText(mixed $value, int $maximum): string
    {
        $text = trim((string) $value);
        if ($text === '' || strlen($text) > $maximum) {
            throw new InvalidArgumentException('text');
        }
        return $text;
    }

    private static function optionalText(mixed $value, int $maximum): string
    {
        $text = trim((string) $value);
        if (strlen($text) > $maximum) {
            throw new InvalidArgumentException('text');
        }
        return $text;
    }
}
