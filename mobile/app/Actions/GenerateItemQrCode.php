<?php

namespace App\Actions;

use App\NativeComponents\OpenItemLink;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateItemQrCode
{
    private const int MODULE_SIZE = 16;

    private const int QUIET_ZONE = 4;

    /**
     * @return array{path: string, url: string}
     */
    public function handle(int $id, string $name): array
    {
        $url = OpenItemLink::urlFor($id);
        $path = storage_path('app/qr-codes/'.(Str::slug($name) ?: 'item').'-'.$id.'.png');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->png($url));

        return ['path' => $path, 'url' => $url];
    }

    private function png(string $url): string
    {
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth() + self::QUIET_ZONE * 2;
        $pixels = $modules * self::MODULE_SIZE;

        $rows = '';

        for ($y = 0; $y < $modules; $y++) {
            $bits = '';

            for ($x = 0; $x < $modules; $x++) {
                $dark = $this->isDark($matrix, $x - self::QUIET_ZONE, $y - self::QUIET_ZONE);
                $bits .= str_repeat($dark ? '0' : '1', self::MODULE_SIZE);
            }

            $row = "\0".implode('', array_map(
                fn (string $byte): string => chr(bindec(str_pad($byte, 8, '1'))),
                str_split($bits, 8),
            ));

            $rows .= str_repeat($row, self::MODULE_SIZE);
        }

        return "\x89PNG\r\n\x1a\n"
            .$this->chunk('IHDR', pack('NNCCCCC', $pixels, $pixels, 1, 0, 0, 0, 0))
            .$this->chunk('IDAT', gzcompress($rows, 9))
            .$this->chunk('IEND', '');
    }

    private function isDark(ByteMatrix $matrix, int $x, int $y): bool
    {
        $size = $matrix->getWidth();

        return $x >= 0 && $y >= 0 && $x < $size && $y < $size && $matrix->get($x, $y) === 1;
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
