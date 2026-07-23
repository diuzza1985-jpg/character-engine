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
    /**
     * Formato "screenshot" (analisi 21/07, sez. 3 opzione A): stessa immagine di base degli
     * altri formati, overlay diverso — bolle di messaggistica invece del velo scuro + testo
     * centrato di overlay(). $messages alterna mittenti per posizione (indice pari = ricevuto,
     * a sinistra; dispari = inviato da {character}, a destra) — non serve altra informazione,
     * lo stesso principio "alternanza" di una vera conversazione a due.
     */
    public function overlayChatBubbles(string $imageBinary, array $messages): string
    {
        $messages = array_values(array_filter(
            array_map(fn (string $m) => $this->stripEmoji($m), $messages),
            fn (string $m) => $m !== ''
        ));
        if (empty($messages)) {
            throw new RuntimeException('Nessun messaggio valido da sovrapporre come conversazione.');
        }

        $image = imagecreatefromstring($imageBinary);
        if (! $image) {
            throw new RuntimeException("Impossibile leggere l'immagine per sovrapporre la conversazione.");
        }
        imagesavealpha($image, true);
        imagealphablending($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $fontPath = self::FONT_PATH;
        if (! file_exists($fontPath)) {
            throw new RuntimeException("Font non trovato: {$fontPath}");
        }

        // Velo più leggero di overlay() (72 di alpha): qui il contrasto lo fanno soprattutto le
        // bolle piene, non serve oscurare la foto quanto per il testo bianco su fondo scuro.
        $veilColor = imagecolorallocatealpha($image, 0, 0, 0, 45);
        imagefilledrectangle($image, 0, 0, $width, $height, $veilColor);

        $fontSize = max(18, (int) round($width / 28));
        $bubblePaddingX = (int) round($fontSize * 0.9);
        $bubblePaddingY = (int) round($fontSize * 0.6);
        $lineHeight = (int) round($fontSize * 1.3);
        $maxBubbleTextWidth = (int) round($width * 0.58);
        $sideMargin = (int) round($width * 0.06);
        $bubbleGap = (int) round($fontSize * 0.8);

        // Colori stile app di messaggistica: grigio chiaro per i messaggi ricevuti (testo scuro),
        // blu per quelli "inviati" (testo bianco) — stessa alternanza sinistra/destra di una
        // vera conversazione a due, senza bisogno di sapere davvero chi scrive cosa.
        $receivedBg = imagecolorallocate($image, 233, 233, 235);
        $receivedText = imagecolorallocate($image, 30, 30, 30);
        $sentBg = imagecolorallocate($image, 0, 132, 255);
        $sentText = imagecolorallocate($image, 255, 255, 255);

        // Prima passata: calcola l'altezza di ogni bolla per centrare l'intero blocco sull'asse
        // verticale, stesso principio di overlay()/StoryComposerService::compose() — il testo
        // non è mai ancorato in alto/basso a caso, ma bilanciato sull'immagine.
        $bubbles = [];
        $totalHeight = 0;
        foreach ($messages as $i => $message) {
            $lines = $this->wrapText($message, $fontPath, $fontSize, $maxBubbleTextWidth - ($bubblePaddingX * 2));
            $bubbleHeight = ($lineHeight * count($lines)) + ($bubblePaddingY * 2);
            $bubbles[] = ['lines' => $lines, 'height' => $bubbleHeight, 'sent' => $i % 2 === 1];
            $totalHeight += $bubbleHeight + $bubbleGap;
        }
        $totalHeight -= $bubbleGap;

        $y = (int) round(($height - $totalHeight) / 2);

        foreach ($bubbles as $bubble) {
            $lineWidths = array_map(function (string $line) use ($fontPath, $fontSize) {
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
                return abs($bbox[4] - $bbox[0]);
            }, $bubble['lines']);
            $textWidth = max($lineWidths);
            $bubbleWidth = $textWidth + ($bubblePaddingX * 2);

            $x = $bubble['sent'] ? $width - $sideMargin - $bubbleWidth : $sideMargin;
            $bg = $bubble['sent'] ? $sentBg : $receivedBg;
            $textColor = $bubble['sent'] ? $sentText : $receivedText;

            $radius = min(18, (int) round($bubble['height'] / 3));
            $this->drawRoundedRect($image, $x, $y, $x + $bubbleWidth, $y + $bubble['height'], $radius, $bg);

            $textY = $y + $bubblePaddingY + $fontSize;
            foreach ($bubble['lines'] as $line) {
                imagettftext($image, $fontSize, 0, $x + $bubblePaddingX, $textY, $textColor, $fontPath, $line);
                $textY += $lineHeight;
            }

            $y += $bubble['height'] + $bubbleGap;
        }

        ob_start();
        imagepng($image);
        $output = ob_get_clean();
        imagedestroy($image);
        return $output;
    }

    /**
     * GD non offre un rettangolo arrotondato nativo (verificato: niente
     * imagefilledroundedrectangle in questa versione) — due rettangoli sovrapposti più quattro
     * cerchi negli angoli danno lo stesso risultato visivo, senza dipendenze esterne.
     */
    private function drawRoundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
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
