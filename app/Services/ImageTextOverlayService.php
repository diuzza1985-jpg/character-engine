<?php
namespace App\Services;
use RuntimeException;
class ImageTextOverlayService
{
    private const FONT_PATH = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
    public function overlay(string $imageBinary, string $text): string
    {
        $text = $this->stripEmoji($text);
        $image = imagecreatefromstring($imageBinary);
        if (! $image) {
            throw new RuntimeException("Impossibile leggere l'immagine per sovrapporre il testo.");
        }
        imagesavealpha($image, true);
        imagealphablending($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $fontPath = self::FONT_PATH;
        if (! file_exists($fontPath)) {
            throw new RuntimeException("Font non trovato: {$fontPath}");
        }
        // Velo scuro leggero su tutta l'immagine (non solo sulla fascia di testo),
        // così il taglio netto sopra/sotto sparisce e la foto resta leggibile ovunque.
        $veilColor = imagecolorallocatealpha($image, 0, 0, 0, 72);
        imagefilledrectangle($image, 0, 0, $width, $height, $veilColor);
        $fontSize = max(24, (int) round($width / 18));
        $padding = (int) round($width * 0.08);
        $maxTextWidth = $width - ($padding * 2);
        $lines = $this->wrapText($text, $fontPath, $fontSize, $maxTextWidth);
        $lineHeight = (int) round($fontSize * 1.35);
        $textBlockHeight = $lineHeight * count($lines);
        $white = imagecolorallocate($image, 255, 255, 255);
        $y = (int) round(($height - $textBlockHeight) / 2) + $lineHeight;
        foreach ($lines as $line) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $lineWidth = abs($bbox[4] - $bbox[0]);
            $x = (int) round(($width - $lineWidth) / 2);
            imagettftext($image, $fontSize, 0, $x, $y, $white, $fontPath, $line);
            $y += $lineHeight;
        }
        ob_start();
        imagepng($image);
        $output = ob_get_clean();
        imagedestroy($image);
        return $output;
    }
    private function stripEmoji(string $text): string
    {
        $pattern = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u';
        return trim(preg_replace($pattern, '', $text));
    }
    private function wrapText(string $text, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = trim($current . ' ' . $word);
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $candidate);
            $candidateWidth = abs($bbox[4] - $bbox[0]);
            if ($candidateWidth > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }
}
