<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ZipReader
{
    private $entries = [];

    public function __construct(string $path)
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('无法读取上传的压缩文件。');
        }

        $this->parseCentralDirectory($bytes);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->entries);
    }

    public function get(string $name): string
    {
        if (!$this->has($name)) {
            throw new RuntimeException('压缩包中缺少文件：' . $name);
        }

        return $this->entries[$name];
    }

    private function parseCentralDirectory(string $bytes): void
    {
        $eocdSignature = pack('V', 0x06054b50);
        $eocdOffset = strrpos($bytes, $eocdSignature);
        if ($eocdOffset === false) {
            throw new RuntimeException('不是有效的 ZIP 或 XLSX 文件。');
        }

        $eocd = unpack(
            'Vsignature/vdisk/vcentralDisk/vdiskEntries/ventries/VcentralSize/VcentralOffset/vcommentLength',
            substr($bytes, $eocdOffset, 22)
        );

        $offset = (int) $eocd['centralOffset'];
        $entries = (int) $eocd['entries'];

        for ($index = 0; $index < $entries; $index++) {
            $header = unpack(
                'Vsignature/vversionMade/vversionNeeded/vflags/vcompression/vmodTime/vmodDate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternalAttr/VexternalAttr/VlocalOffset',
                substr($bytes, $offset, 46)
            );

            if ((int) $header['signature'] !== 0x02014b50) {
                throw new RuntimeException('ZIP 中央目录结构损坏。');
            }

            $nameStart = $offset + 46;
            $name = substr($bytes, $nameStart, (int) $header['nameLength']);
            $localOffset = (int) $header['localOffset'];

            $localHeader = unpack(
                'Vsignature/vversionNeeded/vflags/vcompression/vmodTime/vmodDate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength',
                substr($bytes, $localOffset, 30)
            );

            if ((int) $localHeader['signature'] !== 0x04034b50) {
                throw new RuntimeException('ZIP 本地文件头损坏。');
            }

            $dataStart = $localOffset + 30 + (int) $localHeader['nameLength'] + (int) $localHeader['extraLength'];
            $compressedData = substr($bytes, $dataStart, (int) $header['compressedSize']);
            $compression = (int) $header['compression'];

            if ($compression === 0) {
                $content = $compressedData;
            } elseif ($compression === 8) {
                $content = gzinflate($compressedData);
                if ($content === false) {
                    throw new RuntimeException('ZIP 解压失败：' . $name);
                }
            } else {
                throw new RuntimeException('不支持的 ZIP 压缩方式：' . $compression);
            }

            $this->entries[$name] = $content;
            $offset = $nameStart + (int) $header['nameLength'] + (int) $header['extraLength'] + (int) $header['commentLength'];
        }
    }
}
