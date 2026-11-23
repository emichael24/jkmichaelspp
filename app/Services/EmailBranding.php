<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brand settings and the HTML layout shared by every email the platform sends:
 * campaigns, CRM correspondence and notifications.
 */
class EmailBranding
{
    public const KEY = 'email_branding';

    public const DEFAULT_ACCENT = '#c2410c';

    public function get(): array
    {
        $settings = app(PlatformSettings::class);
        $saved = (array) $settings->get(self::KEY, []);
        $logo = $saved['logo_path'] ?? null;
        $hex = fn ($value, $fallback) => is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $fallback;

        return [
            'brand_name' => trim((string) ($saved['brand_name'] ?? '')) ?: ((string) $settings->get('platform_name') ?: 'JK Michaels'),
            'logo_path' => $logo,
            'logo_url' => $logo ? Storage::disk('public')->url($logo) : null,
            'accent_color' => $hex($saved['accent_color'] ?? null, self::DEFAULT_ACCENT),
            'header_background' => $hex($saved['header_background'] ?? null, '#ffffff'),
            'footer_text' => (string) ($saved['footer_text'] ?? ''),
        ];
    }

    public function update(array $values, ?UploadedFile $logo = null, bool $removeLogo = false): array
    {
        $current = $this->get();
        $path = $current['logo_path'];
        if (($logo || $removeLogo) && $path) {
            Storage::disk('public')->delete($path);
            $path = null;
        }
        if ($logo) {
            // Email clients fetch the logo anonymously, so it lives on the public disk.
            $path = $logo->storeAs('branding', 'email-logo-'.Str::lower(Str::random(12)).'.'.$logo->extension(), 'public');
        }
        app(PlatformSettings::class)->put([self::KEY => [
            'brand_name' => $values['brand_name'] ?? $current['brand_name'],
            'accent_color' => $values['accent_color'] ?? $current['accent_color'],
            'header_background' => $values['header_background'] ?? $current['header_background'],
            'footer_text' => $values['footer_text'] ?? '',
            'logo_path' => $path,
        ]]);

        return $this->get();
    }

    /**
     * Wrap already-sanitized HTML content in the branded layout.
     */
    public function render(string $content, ?string $preferencesUrl = null, ?string $preheader = null, ?array $branding = null): string
    {
        return view('mail.composed-message', [
            'content' => $content,
            'preferencesUrl' => $preferencesUrl,
            'preheader' => $preheader,
            'brand' => $branding ?? $this->get(),
        ])->render();
    }

    /** Replace {{first_name}} / {{full_name}} merge tags; $html escapes the inserted name. */
    public static function personalize(string $text, ?string $firstName, ?string $fullName, bool $html = false): string
    {
        $first = trim((string) $firstName) ?: 'there';
        $full = trim((string) $fullName) ?: $first;
        $escape = fn ($value) => $html ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;

        return preg_replace_callback('/\{\{\s*(first_name|full_name|name)\s*\}\}/i',
            fn ($match) => $escape(strtolower($match[1]) === 'first_name' ? $first : $full), $text) ?? $text;
    }
}
