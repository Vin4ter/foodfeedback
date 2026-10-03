<?php
/**
 * Мини-генератор QR-кодов на чистом PHP (без GD и сторонних библиотек).
 * Режим byte, коррекция ошибок M, версии 1-10 (до ~210 байт). Работает на PHP 7.2+.
 * Вывод: PNG (нужен zlib, он есть в стандартной сборке) и SVG.
 */
class QrMini
{
    // [ec на блок, блоков группы1, данных в блоке г1, блоков группы2, данных в блоке г2]
    private static $TABLE = array(
        1 => array(10, 1, 16, 0, 0), 2 => array(16, 1, 28, 0, 0), 3 => array(26, 1, 44, 0, 0),
        4 => array(18, 2, 32, 0, 0), 5 => array(24, 2, 43, 0, 0), 6 => array(16, 4, 27, 0, 0),
        7 => array(18, 4, 31, 0, 0), 8 => array(22, 2, 38, 2, 39), 9 => array(22, 3, 36, 2, 37),
        10 => array(26, 4, 43, 1, 44),
    );
    private static $ALIGN = array(
        1 => array(), 2 => array(6, 18), 3 => array(6, 22), 4 => array(6, 26), 5 => array(6, 30),
        6 => array(6, 34), 7 => array(6, 22, 38), 8 => array(6, 24, 42), 9 => array(6, 26, 46),
        10 => array(6, 28, 50),
    );
    private static $exp = null;
    private static $log = null;

    private $size = 0;
    private $mod = array();
    private $fn = array();

    /** Возвращает матрицу модулей: массив строк, значения 0/1 */
    public static function matrix($text)
    {
        self::init();
        $q = new QrMini();
        return $q->build((string)$text);
    }

    public static function png($mod, $scale = 6, $border = 4)
    {
        $size = count($mod);
        $white = str_repeat('1', $border * $scale);
        $blank = self::rowBits(array_fill(0, $size, 0), $scale, $white);
        $raw = str_repeat($blank, $border * $scale);
        foreach ($mod as $row) {
            $raw .= str_repeat(self::rowBits($row, $scale, $white), $scale);
        }
        $raw .= str_repeat($blank, $border * $scale);
        $w = ($size + 2 * $border) * $scale;
        return "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', pack('NNCCCCC', $w, $w, 1, 0, 0, 0, 0))
            . self::chunk('IDAT', gzcompress($raw))
            . self::chunk('IEND', '');
    }

    public static function svg($mod, $border = 4)
    {
        $size = count($mod);
        $d = '';
        foreach ($mod as $y => $row) {
            foreach ($row as $x => $v) {
                if ($v) $d .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
            }
        }
        $vb = $size + 2 * $border;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $vb . ' ' . $vb . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
    }

    // ---------- PNG helpers ----------
    private static function chunk($type, $data)
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private static function rowBits($row, $scale, $white)
    {
        $s = $white;
        foreach ($row as $v) $s .= str_repeat($v ? '0' : '1', $scale);
        $s .= $white;
        $s .= str_repeat('1', (8 - strlen($s) % 8) % 8);
        $out = "\x00";
        for ($i = 0; $i < strlen($s); $i += 8) $out .= chr(bindec(substr($s, $i, 8)));
        return $out;
    }

    // ---------- Reed-Solomon ----------
    private static function init()
    {
        if (self::$exp !== null) return;
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 256) $x ^= 0x11d;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function gmul($a, $b)
    {
        if ($a == 0 || $b == 0) return 0;
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    private static function rsGen($n)
    {
        $g = array(1);
        for ($i = 0; $i < $n; $i++) {
            $ng = array_fill(0, count($g) + 1, 0);
            for ($j = 0; $j < count($g); $j++) {
                $ng[$j] ^= $g[$j];
                $ng[$j + 1] ^= self::gmul($g[$j], self::$exp[$i]);
            }
            $g = $ng;
        }
        return $g;
    }

    private static function rsEncode($data, $n)
    {
        $g = self::rsGen($n);
        $res = array_fill(0, $n, 0);
        foreach ($data as $b) {
            $f = $b ^ $res[0];
            array_shift($res);
            $res[] = 0;
            if ($f) {
                for ($i = 0; $i < $n; $i++) $res[$i] ^= self::gmul($g[$i + 1], $f);
            }
        }
        return $res;
    }

    private static function put(&$bits, $val, $n)
    {
        for ($i = $n - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1;
    }

    private static function bit($v, $i)
    {
        return ($v >> $i) & 1;
    }

    // ---------- Сборка матрицы ----------
    private function setf($x, $y, $d)
    {
        $this->mod[$y][$x] = $d ? 1 : 0;
        $this->fn[$y][$x] = true;
    }

    private function build($text)
    {
        $data = array_values(unpack('C*', $text));
        $ver = 0;
        for ($v = 1; $v <= 10; $v++) {
            $t = self::$TABLE[$v];
            $cap = $t[1] * $t[2] + $t[3] * $t[4];
            $cc = $v < 10 ? 8 : 16;
            if (4 + $cc + 8 * count($data) <= $cap * 8) { $ver = $v; break; }
        }
        if ($ver == 0) throw new Exception('QR: слишком длинный текст');
        $t = self::$TABLE[$ver];
        $cap = $t[1] * $t[2] + $t[3] * $t[4];
        $cc = $ver < 10 ? 8 : 16;

        $bits = array();
        self::put($bits, 4, 4);
        self::put($bits, count($data), $cc);
        foreach ($data as $b) self::put($bits, $b, 8);
        self::put($bits, 0, min(4, $cap * 8 - count($bits)));
        while (count($bits) % 8) $bits[] = 0;
        $cw = array();
        for ($i = 0; $i < count($bits); $i += 8) {
            $v = 0;
            for ($k = 0; $k < 8; $k++) $v = ($v << 1) | $bits[$i + $k];
            $cw[] = $v;
        }
        $pad = array(0xEC, 0x11);
        $k = 0;
        while (count($cw) < $cap) { $cw[] = $pad[$k % 2]; $k++; }

        $blocks = array();
        $pos = 0;
        for ($i = 0; $i < $t[1]; $i++) { $blocks[] = array_slice($cw, $pos, $t[2]); $pos += $t[2]; }
        for ($i = 0; $i < $t[3]; $i++) { $blocks[] = array_slice($cw, $pos, $t[4]); $pos += $t[4]; }
        $ecs = array();
        $maxd = 0;
        foreach ($blocks as $b) {
            $ecs[] = self::rsEncode($b, $t[0]);
            $maxd = max($maxd, count($b));
        }
        $final = array();
        for ($i = 0; $i < $maxd; $i++) {
            foreach ($blocks as $b) { if ($i < count($b)) $final[] = $b[$i]; }
        }
        for ($i = 0; $i < $t[0]; $i++) {
            foreach ($ecs as $e) $final[] = $e[$i];
        }

        $size = 17 + 4 * $ver;
        $this->size = $size;
        $this->mod = array_fill(0, $size, array_fill(0, $size, 0));
        $this->fn = array_fill(0, $size, array_fill(0, $size, false));

        // timing
        for ($i = 0; $i < $size; $i++) {
            $this->setf(6, $i, $i % 2 == 0);
            $this->setf($i, 6, $i % 2 == 0);
        }
        // finder + separators
        $finders = array(array(0, 0), array($size - 7, 0), array(0, $size - 7));
        foreach ($finders as $f) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $xx = $f[0] + $dx;
                    $yy = $f[1] + $dy;
                    if ($xx < 0 || $yy < 0 || $xx >= $size || $yy >= $size) continue;
                    $dark = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                        && ($dx == 0 || $dx == 6 || $dy == 0 || $dy == 6
                            || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4)));
                    $this->setf($xx, $yy, $dark);
                }
            }
        }
        // alignment
        $ap = self::$ALIGN[$ver];
        $n = count($ap);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i == 0 && $j == 0) || ($i == 0 && $j == $n - 1) || ($i == $n - 1 && $j == 0)) continue;
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setf($ap[$i] + $dx, $ap[$j] + $dy, max(abs($dx), abs($dy)) != 1);
                    }
                }
            }
        }
        $this->drawFormat(0); // резервируем область формата
        if ($ver >= 7) {
            $rem = $ver;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $vb = ($ver << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->setf($a, $b, self::bit($vb, $i));
                $this->setf($b, $a, self::bit($vb, $i));
            }
        }

        // размещение данных зигзагом
        $i = 0;
        $total = count($final) * 8;
        $right = $size - 1;
        while ($right > 0) {
            if ($right <= 6) $right--;
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $xx = $right - $j;
                    $upward = ((($right + 1) & 2) == 0);
                    $yy = $upward ? ($size - 1 - $vert) : $vert;
                    if (!$this->fn[$yy][$xx] && $i < $total) {
                        $this->mod[$yy][$xx] = ($final[$i >> 3] >> (7 - ($i & 7))) & 1;
                        $i++;
                    }
                }
            }
            $right -= 2;
        }

        // выбор лучшей маски
        $best = 0;
        $bp = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->applyMask($m);
            $this->drawFormat($m);
            $pp = $this->penalty();
            if ($pp < $bp) { $bp = $pp; $best = $m; }
            $this->applyMask($m);
        }
        $this->applyMask($best);
        $this->drawFormat($best);
        return $this->mod;
    }

    private function drawFormat($mask)
    {
        $size = $this->size;
        $data = $mask; // уровень коррекции M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bs = (($data << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i < 6; $i++) $this->setf(8, $i, self::bit($bs, $i));
        $this->setf(8, 7, self::bit($bs, 6));
        $this->setf(8, 8, self::bit($bs, 7));
        $this->setf(7, 8, self::bit($bs, 8));
        for ($i = 9; $i < 15; $i++) $this->setf(14 - $i, 8, self::bit($bs, $i));
        for ($i = 0; $i < 8; $i++) $this->setf($size - 1 - $i, 8, self::bit($bs, $i));
        for ($i = 8; $i < 15; $i++) $this->setf(8, $size - 15 + $i, self::bit($bs, $i));
        $this->setf(8, $size - 8, true);
    }

    private function applyMask($m)
    {
        $size = $this->size;
        for ($yy = 0; $yy < $size; $yy++) {
            for ($xx = 0; $xx < $size; $xx++) {
                switch ($m) {
                    case 0: $inv = (($xx + $yy) % 2 == 0); break;
                    case 1: $inv = ($yy % 2 == 0); break;
                    case 2: $inv = ($xx % 3 == 0); break;
                    case 3: $inv = (($xx + $yy) % 3 == 0); break;
                    case 4: $inv = ((intdiv($xx, 3) + intdiv($yy, 2)) % 2 == 0); break;
                    case 5: $inv = ((($xx * $yy) % 2) + (($xx * $yy) % 3) == 0); break;
                    case 6: $inv = (((($xx * $yy) % 2) + (($xx * $yy) % 3)) % 2 == 0); break;
                    default: $inv = (((($xx + $yy) % 2) + (($xx * $yy) % 3)) % 2 == 0); break;
                }
                if ($inv && !$this->fn[$yy][$xx]) $this->mod[$yy][$xx] ^= 1;
            }
        }
    }

    private function penalty()
    {
        $size = $this->size;
        $lines = array();
        for ($y = 0; $y < $size; $y++) $lines[] = implode('', $this->mod[$y]);
        for ($x = 0; $x < $size; $x++) {
            $s = '';
            for ($y = 0; $y < $size; $y++) $s .= $this->mod[$y][$x];
            $lines[] = $s;
        }
        $p = 0;
        foreach ($lines as $s) {
            preg_match_all('/0{5,}|1{5,}/', $s, $mm);
            foreach ($mm[0] as $run) $p += 3 + strlen($run) - 5;
            $p += 40 * substr_count($s, '10111010000') + 40 * substr_count($s, '00001011101');
        }
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $v = $this->mod[$y][$x];
                if ($v == $this->mod[$y][$x + 1] && $v == $this->mod[$y + 1][$x] && $v == $this->mod[$y + 1][$x + 1]) $p += 3;
            }
        }
        $dark = 0;
        for ($y = 0; $y < $size; $y++) $dark += array_sum($this->mod[$y]);
        $k = abs($dark * 20 - $size * $size * 10);
        $k = intdiv($k + $size * $size - 1, $size * $size) - 1;
        $p += $k * 10;
        return $p;
    }
}
