<?php

namespace App\Services\Qr;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

class BrandedQrCodeService
{
    /**
     * @return array{primary: array{0: int, 1: int, 2: int}, primary_hex: string, secondary_hex: string, glow_hex: string}
     */
    private function palette(string $tone): array
    {
        return match ($tone) {
            'enrollment' => [
                'primary' => [6, 95, 70],
                'primary_hex' => '#065f46',
                'secondary_hex' => '#0f766e',
                'glow_hex' => '#5eead4',
            ],
            default => [
                'primary' => [30, 41, 135],
                'primary_hex' => '#1e2987',
                'secondary_hex' => '#465fff',
                'glow_hex' => '#a5b4fc',
            ],
        };
    }

    public function code(string $url, string $tone = 'event'): string
    {
        $palette = $this->palette($tone);

        return (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_COMPACT => true],
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 720,
            margin: 48,
            foregroundColor: new Color(...$palette['primary']),
            backgroundColor: new Color(255, 255, 255),
        ))->build()->getString();
    }

    public function poster(
        string $url,
        string $eyebrow,
        string $title,
        string $subtitle,
        string $tone = 'event',
        string $footer = 'Powered by GymAtlas',
    ): string {
        $palette = $this->palette($tone);
        $qrArtwork = $this->innerSvg($this->code($url, $tone), true);
        $eyebrow = $this->escape($this->truncate($eyebrow, 42));
        $title = $this->escape($this->truncate($title, 48));
        $subtitle = $this->escape($this->truncate($subtitle, 76));
        $footer = $this->escape($this->truncate($footer, 70));

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350" role="img" aria-labelledby="title description">
  <title id="title">{$title} QR poster</title>
  <desc id="description">Scan this GymAtlas QR code to continue.</desc>
  <defs>
    <linearGradient id="background" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$palette['primary_hex']}"/>
      <stop offset="0.58" stop-color="{$palette['secondary_hex']}"/>
      <stop offset="1" stop-color="#111827"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.82" cy="0.05" r="0.78">
      <stop offset="0" stop-color="{$palette['glow_hex']}" stop-opacity="0.7"/>
      <stop offset="1" stop-color="{$palette['glow_hex']}" stop-opacity="0"/>
    </radialGradient>
    <filter id="shadow" x="-20%" y="-20%" width="140%" height="150%">
      <feDropShadow dx="0" dy="28" stdDeviation="32" flood-color="#020617" flood-opacity="0.34"/>
    </filter>
  </defs>
  <rect width="1080" height="1350" rx="52" fill="url(#background)"/>
  <rect width="1080" height="1350" rx="52" fill="url(#glow)"/>
  <circle cx="970" cy="175" r="190" fill="none" stroke="#ffffff" stroke-opacity="0.09" stroke-width="2"/>
  <circle cx="970" cy="175" r="128" fill="none" stroke="#ffffff" stroke-opacity="0.09" stroke-width="2"/>
  <g font-family="Inter, Arial, sans-serif" fill="#ffffff">
    <g transform="translate(76 72)">
      <rect width="68" height="68" rx="19" fill="#ffffff" fill-opacity="0.16" stroke="#ffffff" stroke-opacity="0.2"/>
      <path d="M20 34h28M34 20v28M24 24l20 20M44 24L24 44" stroke="#ffffff" stroke-width="5" stroke-linecap="round"/>
      <text x="88" y="44" font-size="34" font-weight="750" letter-spacing="-0.8">GymAtlas</text>
    </g>
    <text x="540" y="200" text-anchor="middle" font-size="24" font-weight="700" letter-spacing="5" fill="#ffffff" fill-opacity="0.78">{$eyebrow}</text>
    <text x="540" y="264" text-anchor="middle" font-size="54" font-weight="800" letter-spacing="-1.5">{$title}</text>
    <text x="540" y="310" text-anchor="middle" font-size="24" font-weight="500" fill="#ffffff" fill-opacity="0.76">{$subtitle}</text>
  </g>
  <g filter="url(#shadow)">
    <rect x="120" y="354" width="840" height="840" rx="54" fill="#ffffff"/>
    <rect x="137" y="371" width="806" height="806" rx="42" fill="none" stroke="#e2e8f0" stroke-width="2"/>
    <g transform="translate(180 414) scale(0.882352941)">{$qrArtwork}</g>
    <rect x="484" y="718" width="112" height="112" rx="26" fill="#ffffff"/>
    <rect x="493" y="727" width="94" height="94" rx="22" fill="{$palette['secondary_hex']}"/>
    <path d="M517 774h46M540 751v46M523 757l34 34M557 757l-34 34" stroke="#ffffff" stroke-width="7" stroke-linecap="round"/>
  </g>
  <g font-family="Inter, Arial, sans-serif" text-anchor="middle" fill="#ffffff">
    <text x="540" y="1260" font-size="31" font-weight="750">Open your camera and scan to continue</text>
    <text x="540" y="1304" font-size="20" font-weight="500" fill-opacity="0.68">{$footer}</text>
  </g>
</svg>
SVG;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function innerSvg(string $svg, bool $excludeImages = false): string
    {
        $document = new \DOMDocument;
        $document->loadXML($svg, LIBXML_NONET);
        $content = '';

        foreach ($document->documentElement?->childNodes ?? [] as $child) {
            if ($excludeImages && $child instanceof \DOMElement && $child->localName === 'image') {
                continue;
            }

            $content .= $document->saveXML($child);
        }

        return $content;
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strimwidth(trim($value), 0, $length, '…', 'UTF-8');
    }
}
