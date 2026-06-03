<?php

namespace Aghfatehi\Zatca\Services;

class SvgQrGenerator
{
    private const GF256_EXP = [
        1, 2, 4, 8, 16, 32, 64, 128, 29, 58, 116, 232, 205, 135, 19, 38,
        76, 152, 45, 90, 180, 117, 234, 201, 143, 3, 6, 12, 24, 48, 96, 192,
        157, 39, 78, 156, 37, 74, 148, 53, 106, 212, 181, 119, 238, 193, 159, 35,
        70, 140, 5, 10, 20, 40, 80, 160, 93, 186, 105, 210, 185, 111, 222, 161,
        95, 190, 97, 194, 153, 47, 94, 188, 101, 202, 137, 15, 30, 60, 120, 240,
        253, 231, 211, 187, 107, 214, 177, 127, 254, 225, 223, 163, 91, 182, 113, 226,
        217, 175, 67, 134, 17, 34, 68, 136, 13, 26, 52, 104, 208, 189, 103, 206,
        129, 31, 62, 124, 248, 237, 199, 147, 59, 118, 236, 197, 151, 51, 102, 204,
        133, 23, 46, 92, 184, 109, 218, 169, 79, 158, 33, 66, 132, 21, 42, 84,
        168, 77, 154, 41, 82, 164, 85, 170, 73, 146, 57, 114, 228, 213, 183, 115,
        230, 209, 191, 99, 198, 145, 63, 126, 252, 229, 215, 179, 123, 246, 241, 255,
        227, 219, 171, 75, 150, 49, 98, 196, 149, 55, 110, 220, 165, 87, 174, 65,
        130, 25, 50, 100, 200, 141, 7, 14, 28, 56, 112, 224, 221, 167, 83, 166,
        81, 162, 89, 178, 121, 242, 249, 239, 195, 155, 43, 86, 172, 69, 138, 9,
        18, 36, 72, 144, 61, 122, 244, 245, 247, 243, 251, 235, 203, 139, 11, 22,
        44, 88, 176, 125, 250, 233, 207, 131, 27, 54, 108, 216, 173, 71, 142, 1,
    ];

    private const GF256_LOG = [
        -1, 0, 1, 25, 2, 50, 26, 198, 3, 223, 51, 238, 27, 104, 199, 75,
        4, 100, 224, 14, 52, 141, 239, 129, 28, 193, 105, 248, 200, 8, 76, 113,
        5, 138, 101, 47, 225, 36, 15, 33, 53, 147, 142, 218, 240, 18, 130, 69,
        29, 181, 194, 125, 106, 39, 249, 185, 201, 154, 9, 120, 77, 228, 114, 166,
        6, 191, 139, 98, 102, 221, 48, 253, 226, 152, 37, 179, 16, 145, 34, 136,
        54, 208, 148, 206, 143, 150, 219, 189, 241, 210, 19, 92, 131, 56, 70, 64,
        30, 66, 182, 163, 195, 72, 126, 110, 107, 58, 40, 84, 250, 133, 186, 61,
        202, 94, 155, 159, 10, 21, 121, 43, 78, 212, 229, 172, 115, 243, 167, 87,
        7, 112, 192, 247, 140, 128, 99, 13, 103, 74, 222, 237, 49, 197, 254, 24,
        227, 165, 153, 119, 38, 184, 180, 124, 17, 68, 146, 217, 35, 32, 137, 46,
        55, 63, 209, 91, 149, 188, 207, 205, 144, 135, 151, 178, 220, 252, 190, 97,
        242, 86, 211, 171, 20, 42, 93, 158, 132, 60, 57, 83, 71, 109, 65, 162,
        31, 45, 67, 216, 183, 123, 164, 118, 196, 23, 73, 236, 127, 12, 111, 246,
        108, 161, 59, 82, 41, 157, 85, 170, 251, 96, 134, 177, 187, 204, 62, 90,
        203, 89, 95, 176, 156, 169, 160, 81, 11, 245, 22, 235, 122, 117, 44, 215,
        79, 174, 213, 233, 230, 231, 173, 232, 116, 214, 244, 234, 168, 80, 88, 175,
    ];

    private const ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    private array $matrix;
    private int $version;
    private int $size;
    private string $data;

    private static array $versionInfo = [
        [1, 21, 26, 18, 17, 14],
        [2, 25, 44, 34, 32, 28],
        [3, 29, 70, 56, 53, 44],
        [4, 33, 100, 80, 78, 64],
        [5, 37, 134, 108, 106, 86],
        [6, 41, 172, 136, 134, 108],
        [7, 45, 196, 156, 154, 124],
        [8, 49, 242, 194, 192, 154],
        [9, 53, 292, 232, 230, 182],
        [10, 57, 346, 274, 272, 216],
    ];

    public function generate(string $data, int $size = 200): string
    {
        $this->data = $data;
        $this->version = $this->selectVersion(strlen($data));
        $this->size = self::$versionInfo[$this->version - 1][1];
        $this->matrix = array_fill(0, $this->size, array_fill(0, $this->size, -1));

        $this->placeFinderPatterns();
        $this->placeTimingPatterns();
        $this->placeAlignmentPatterns();
        $this->placeFormatInfoReserved();

        $encoded = $this->encodeData();
        $this->placeData($encoded);

        $mask = $this->selectMask();
        $this->applyMask($mask);
        $this->placeFormatInfo($mask);
        $this->placeVersionInfo();

        return $this->renderSvg($size);
    }

    private function selectVersion(int $dataLen): int
    {
        for ($v = 1; $v <= 10; $v++) {
            if ($dataLen <= self::$versionInfo[$v - 1][2]) {
                return $v;
            }
        }

        return 10;
    }

    private function encodeData(): array
    {
        $mode = 0b0100;
        $charCount = strlen($this->data);
        $versionInfo = self::$versionInfo[$this->version - 1];
        $totalDataBytes = $versionInfo[2];
        $totalDataBits = $totalDataBytes * 8;

        $charCountBits = $this->version < 10 ? 8 : 16;

        $bits = [];
        $this->appendBits($bits, $mode, 4);
        $this->appendBits($bits, $charCount, $charCountBits);

        foreach (str_split($this->data) as $char) {
            $this->appendBits($bits, ord($char), 8);
        }

        if (count($bits) > $totalDataBits) {
            throw new \RuntimeException('Data too long for version ' . $this->version);
        }

        $this->appendBits($bits, 0, min(4, $totalDataBits - count($bits)));

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                if ($i + $j < count($bits)) {
                    $byte = ($byte << 1) | $bits[$i + $j];
                }
            }
            $bytes[] = $byte;
        }

        $padBytes = [236, 17];
        $padIdx = 0;
        while (count($bytes) < $totalDataBytes) {
            $bytes[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        $rsBlocks = $this->getRsBlocks();
        $data = [];
        $ec = [];

        foreach ($rsBlocks as $block) {
            $blockData = array_slice($bytes, 0, $block['data']);
            $bytes = array_slice($bytes, $block['data']);
            $blockEc = $this->reedSolomon($blockData, $block['ec']);

            $data = array_merge($data, $blockData);
            $ec = array_merge($ec, $blockEc);
        }

        $interleaved = [];
        $blockCount = count($rsBlocks);
        $maxData = max(array_column($rsBlocks, 'data'));

        for ($i = 0; $i < $maxData; $i++) {
            foreach ($rsBlocks as $bi => $block) {
                $offset = array_sum(array_map(fn($b, $idx) => $idx < $bi ? $b['data'] : 0, array_column($rsBlocks, 'data'), array_keys($rsBlocks)));
                $idx = $offset + $i;
                if ($i < $block['data'] && $idx < count($data)) {
                    $interleaved[] = $data[$idx];
                }
            }
        }

        $interleaved = array_merge($interleaved, $ec);

        return $interleaved;
    }

    private function getRsBlocks(): array
    {
        $blocks = [
            [1 => [1, 26, 19, 7], 2 => [1, 44, 34, 10], 3 => [1, 70, 55, 15], 4 => [1, 100, 80, 20], 5 => [1, 134, 108, 26],
             6 => [2, 86, 68, 18], 7 => [2, 98, 78, 20], 8 => [2, 121, 97, 24], 9 => [2, 146, 116, 30], 10 => [2, 86, 68, 18]],
        ][0][$this->version] ?? [1, 100, 80, 20];

        $numBlocks = $blocks[0];
        $result = [];

        for ($i = 0; $i < $numBlocks; $i++) {
            $result[] = ['data' => $blocks[2], 'ec' => $blocks[3]];
        }

        return $result;
    }

    private function reedSolomon(array $data, int $ecCount): array
    {
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $generator = $this->gfPolyMul($generator, [1, self::GF256_EXP[$i]]);
        }

        $message = array_merge($data, array_fill(0, $ecCount, 0));

        for ($i = 0; $i < count($data); $i++) {
            if ($message[$i] !== 0) {
                $factor = self::GF256_LOG[$message[$i]];
                for ($j = 0; $j < count($generator); $j++) {
                    $message[$i + $j] ^= self::GF256_EXP[($factor + self::GF256_LOG[$generator[$j]]) % 255];
                }
            }
        }

        return array_slice($message, count($data));
    }

    private function gfPolyMul(array $a, array $b): array
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);
        for ($i = 0; $i < count($a); $i++) {
            for ($j = 0; $j < count($b); $j++) {
                if ($a[$i] !== 0 && $b[$j] !== 0) {
                    $logA = self::GF256_LOG[$a[$i]];
                    $logB = self::GF256_LOG[$b[$j]];
                    $result[$i + $j] ^= self::GF256_EXP[($logA + $logB) % 255];
                }
            }
        }
        return $result;
    }

    private function appendBits(array &$bits, int $value, int $numBits): void
    {
        for ($i = $numBits - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function placeFinderPatterns(): void
    {
        foreach ([[0, 0], [$this->size - 7, 0], [0, $this->size - 7]] as [$x, $y]) {
            $this->drawFinderPattern($x, $y);
        }
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $px = $x + $c;
                $py = $y + $r;
                if ($px < 0 || $px >= $this->size || $py < 0 || $py >= $this->size) {
                    continue;
                }
                if ($r >= 0 && $r <= 7 && $c >= 0 && $c <= 7) {
                    $on = ($r == 0 || $r == 6 || $c == 0 || $c == 6)
                        || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                    $this->matrix[$py][$px] = $on ? 1 : 0;
                } else {
                    $this->matrix[$py][$px] = 0;
                }
            }
        }
    }

    private function placeTimingPatterns(): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $this->matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $this->matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }
    }

    private function placeAlignmentPatterns(): void
    {
        $positions = $this->getAlignmentPositions();
        foreach ($positions as [$x, $y]) {
            if ($this->matrix[$y][$x] !== -1) {
                continue;
            }
            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    $px = $x + $c;
                    $py = $y + $r;
                    if ($px < 0 || $px >= $this->size || $py < 0 || $py >= $this->size) {
                        continue;
                    }
                    $on = (abs($r) === 2 || abs($c) === 2) || ($r === 0 && $c === 0);
                    $this->matrix[$py][$px] = $on ? 1 : 0;
                }
            }
        }
    }

    private function getAlignmentPositions(): array
    {
        $positions = [
            1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
            6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
            10 => [6, 28, 50],
        ];

        $pos = $positions[$this->version] ?? [];
        $result = [];
        foreach ($pos as $py) {
            foreach ($pos as $px) {
                $result[] = [$px, $py];
            }
        }

        return $result;
    }

    private function placeFormatInfoReserved(): void
    {
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $this->matrix[8][$i] = 0;
                $this->matrix[$i][8] = 0;
            }
        }
        for ($i = $this->size - 8; $i < $this->size; $i++) {
            $this->matrix[8][$i] = 0;
        }
        for ($i = $this->size - 7; $i < $this->size; $i++) {
            $this->matrix[$i][8] = 0;
        }
        $this->matrix[$this->size - 8][8] = 1;
    }

    private function placeData(array $bytes): void
    {
        $bitIdx = 0;
        $totalBits = count($bytes) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($row = 0; $row < $this->size; $row++) {
                for ($col = 0; $col < 2; $col++) {
                    $x = $right - $col;
                    $y = ($right % 2 === 0) ? ($this->size - 1 - $row) : $row;

                    if ($y < 0 || $y >= $this->size || $x < 0 || $x >= $this->size) {
                        continue;
                    }

                    if ($this->matrix[$y][$x] !== -1) {
                        continue;
                    }

                    if ($bitIdx < $totalBits) {
                        $byteIdx = intdiv($bitIdx, 8);
                        $bitPos = 7 - ($bitIdx % 8);
                        $this->matrix[$y][$x] = ($bytes[$byteIdx] >> $bitPos) & 1;
                        $bitIdx++;
                    } else {
                        $this->matrix[$y][$x] = 0;
                    }
                }
            }
        }
    }

    private function selectMask(): int
    {
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $score = $this->evaluateMask($mask);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
        }

        return $bestMask;
    }

    private function evaluateMask(int $mask): int
    {
        $score = 0;
        $cloned = [];

        foreach ($this->matrix as $y => $row) {
            $cloned[$y] = [];
            foreach ($row as $x => $val) {
                if ($val === -1 || $val === 0 || $val === 1) {
                    $cloned[$y][$x] = $val;
                }
            }
        }

        $this->applyMaskToMatrix($cloned, $mask);

        $runCount = 0;
        $prev = -1;
        $adjacent = 0;

        for ($y = 0; $y < $this->size; $y++) {
            $runCount = 0;
            $prev = -1;
            for ($x = 0; $x < $this->size; $x++) {
                if (isset($cloned[$y][$x]) && ($cloned[$y][$x] === 0 || $cloned[$y][$x] === 1)) {
                    if ($cloned[$y][$x] === $prev) {
                        $runCount++;
                    } else {
                        if ($runCount >= 5) {
                            $score += $runCount + 2;
                        }
                        $runCount = 1;
                        $prev = $cloned[$y][$x];
                    }
                } else {
                    if ($runCount >= 5) {
                        $score += $runCount + 2;
                    }
                    $runCount = 0;
                    $prev = -1;
                }
            }
            if ($runCount >= 5) {
                $score += $runCount + 2;
            }
        }

        for ($x = 0; $x < $this->size; $x++) {
            $runCount = 0;
            $prev = -1;
            for ($y = 0; $y < $this->size; $y++) {
                if (isset($cloned[$y][$x]) && ($cloned[$y][$x] === 0 || $cloned[$y][$x] === 1)) {
                    if ($cloned[$y][$x] === $prev) {
                        $runCount++;
                    } else {
                        if ($runCount >= 5) {
                            $score += $runCount + 2;
                        }
                        $runCount = 1;
                        $prev = $cloned[$y][$x];
                    }
                } else {
                    if ($runCount >= 5) {
                        $score += $runCount + 2;
                    }
                    $runCount = 0;
                    $prev = -1;
                }
            }
            if ($runCount >= 5) {
                $score += $runCount + 2;
            }
        }

        $blocks = 0;
        $blockCount = intdiv($this->size, 2);
        for ($y = 0; $y < $blockCount; $y++) {
            for ($x = 0; $x < $blockCount; $x++) {
                $c1 = $cloned[$y * 2][$x * 2] ?? null;
                $c2 = $cloned[$y * 2][$x * 2 + 1] ?? null;
                $c3 = $cloned[$y * 2 + 1][$x * 2] ?? null;
                $c4 = $cloned[$y * 2 + 1][$x * 2 + 1] ?? null;
                if ($c1 !== null && $c2 !== null && $c3 !== null && $c4 !== null) {
                    if ($c1 === $c2 && $c2 === $c3 && $c3 === $c4) {
                        $blocks++;
                    }
                }
            }
        }
        $score += $blocks * 3;

        $darkCount = 0;
        $totalCount = 0;
        foreach ($cloned as $row) {
            foreach ($row as $val) {
                if ($val === 0 || $val === 1) {
                    $totalCount++;
                    if ($val === 1) {
                        $darkCount++;
                    }
                }
            }
        }

        if ($totalCount > 0) {
            $percent = ($darkCount * 100) / $totalCount;
            $prev = intdiv((int)$percent, 5) * 5;
            $next = $prev + 5;
            $score += min(abs($prev - 50) * 2, abs($next - 50) * 2);
        }

        return $score;
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (isset($this->matrix[$y][$x]) && ($this->matrix[$y][$x] === 0 || $this->matrix[$y][$x] === 1)) {
                    $this->matrix[$y][$x] ^= $this->getMaskBit($mask, $x, $y) ? 1 : 0;
                }
            }
        }
    }

    private function applyMaskToMatrix(array &$matrix, int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (isset($matrix[$y][$x]) && ($matrix[$y][$x] === 0 || $matrix[$y][$x] === 1)) {
                    $matrix[$y][$x] ^= $this->getMaskBit($mask, $x, $y) ? 1 : 0;
                }
            }
        }
    }

    private function getMaskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
            6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
            7 => (($y * $x) % 3 + ($y + $x) % 2) % 2 === 0,
            default => false,
        };
    }

    private function placeFormatInfo(int $mask): void
    {
        $ecLevel = 0b01;
        $formatBits = ($ecLevel << 3) | $mask;
        $generator = 0b10100110111;
        $formatData = $formatBits << 10;

        for ($i = 14; $i >= 10; $i--) {
            if (($formatData >> $i) & 1) {
                $formatData ^= $generator << ($i - 10);
            }
        }

        $formatData = (($formatBits << 10) | $formatData) ^ 0b101010000010010;

        $positions = [];
        for ($i = 0; $i <= 5; $i++) {
            $positions[] = [8, $i];
        }
        $positions[] = [8, 7];
        $positions[] = [8, 8];
        $positions[] = [7, 8];
        for ($i = 5; $i >= 0; $i--) {
            $positions[] = [$i, 8];
        }

        for ($i = 0; $i <= 7; $i++) {
            $positions[] = [$this->size - 1 - $i, 8];
        }
        $positions[] = [8, $this->size - 8];
        for ($i = $this->size - 7; $i <= $this->size - 2; $i++) {
            $positions[] = [8, $i];
        }

        $darkModule = [$this->size - 8, 8];

        foreach ($positions as $i => [$x, $y]) {
            if ($y === $darkModule[0] && $x === $darkModule[1]) {
                continue;
            }
            if ($i < 15 && isset($this->matrix[$y][$x])) {
                $this->matrix[$y][$x] = (($formatData >> (14 - $i)) & 1);
            }
        }

        $this->matrix[$darkModule[0]][$darkModule[1]] = 1;
    }

    private function placeVersionInfo(): void
    {
        if ($this->version < 7) {
            return;
        }

        $data = $this->version;
        $generator = 0b1111101001010;
        $bits = $data << 12;

        for ($i = 17; $i >= 12; $i--) {
            if (($bits >> $i) & 1) {
                $bits ^= $generator << ($i - 12);
            }
        }

        $versionBits = ($data << 12) | $bits;

        $positions = [];
        for ($r = 0; $r < 6; $r++) {
            for ($c = 0; $c < 3; $c++) {
                $positions[] = [$this->size - 11 + $c, $r];
                $positions[] = [$r, $this->size - 11 + $c];
            }
        }

        foreach ($positions as $i => [$x, $y]) {
            if ($i < 18 && isset($this->matrix[$y][$x])) {
                $this->matrix[$y][$x] = (($versionBits >> (17 - $i)) & 1);
            }
        }
    }

    private function renderSvg(int $outputSize): string
    {
        $moduleSize = $outputSize / $this->size;
        $svg = '<svg width="' . $outputSize . '" height="' . $outputSize . '" viewBox="0 0 ' . $this->size . ' ' . $this->size . '" xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges">';

        $svg .= '<rect width="' . $this->size . '" height="' . $this->size . '" fill="#ffffff"/>';

        $paths = ['0' => '', '1' => ''];

        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (isset($this->matrix[$y][$x]) && ($this->matrix[$y][$x] === 0 || $this->matrix[$y][$x] === 1)) {
                    $paths[(string)$this->matrix[$y][$x]] .= 'M' . $x . ' ' . $y . 'h1v1h-1z';
                }
            }
        }

        if ($paths['1'] !== '') {
            $svg .= '<path fill="#000000" d="' . $paths['1'] . '"/>';
        }

        if ($paths['0'] !== '') {
            $svg .= '<path fill="#ffffff" d="' . $paths['0'] . '"/>';
        }

        $svg .= '</svg>';

        return $svg;
    }
}
