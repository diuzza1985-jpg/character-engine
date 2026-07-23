<?php
namespace App\Services;
use RuntimeException;
class StoryComposerService
{
    private const CANVAS_WIDTH = 1080;
    private const CANVAS_HEIGHT = 1920;
    private const FONT_PATH = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
    public function compose(string $baseImageBinary, string $text): string
    {
        $text = $this->stripEmoji($text);
        $source = imagecreatefromstring($baseImageBinary);
        if (! $source) {
            throw new RuntimeException("Impossibile leggere l'immagine di base per la story.");
        }
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $canvas = imagecreatetruecolor(self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
        $scale = max(self::CANVAS_WIDTH / $srcW, self::CANVAS_HEIGHT / $srcH);
        $scaledW = (int) ceil($srcW * $scale);
        $scaledH = (int) ceil($srcH * $scale);
        $resized = imagecreatetruecolor($scaledW, $scaledH);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);
        $offsetX = (int) (($scaledW - self::CANVAS_WIDTH) / 2);
        $offsetY = (int) (($scaledH - self::CANVAS_HEIGHT) / 2);
        imagecopy($canvas, $resized, 0, 0, $offsetX, $offsetY, self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
        imagedestroy($source);
        imagedestroy($resized);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);
        $veilColor = imagecolorallocatealpha($canvas, 0, 0, 0, 70);
        imagefilledrectangle($canvas, 0, 0, self::CANVAS_WIDTH, self::CANVAS_HEIGHT, $veilColor);
        $fontPath = self::FONT_PATH;
        if (! file_exists($fontPath)) {
            throw new RuntimeException("Font non trovato: {$fontPath}");
        }
        $fontSize = 40;
        $padding = 90;
        $maxTextWidth = self::CANVAS_WIDTH - ($padding * 2);
        $lines = $this->wrapText($text, $fontPath, $fontSize, $maxTextWidth);
        $lineHeight = (int) round($fontSize * 1.5);
        $textBlockHeight = $lineHeight * count($lines);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $y = (int) round((self::CANVAS_HEIGHT - $textBlockHeight) / 2) + $lineHeight;
        foreach ($lines as $line) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $lineWidth = abs($bbox[4] - $bbox[0]);
            $x = (int) round((self::CANVAS_WIDTH - $lineWidth) / 2);
            imagettftext($canvas, $fontSize, 0, $x, $y, $white, $fontPath, $line);
            $y += $lineHeight;
        }
        ob_start();
        imagepng($canvas);
        $output = ob_get_clean();
        imagedestroy($canvas);
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
