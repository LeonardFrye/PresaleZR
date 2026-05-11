<?php

declare(strict_types=1);

namespace App\Services;

final class ZipBuilder
{
    private $entries = [];

    public function add(string $name, string $content): void
    {
        $this->entries[] = ['name' => str_replace('\\', '/', $name), 'content' => $content];
    }

    public function output(): string
    {
        $data = '';
        $centralDirectory = '';
        $offset = 0;
        $count = 0;

        foreach ($this->entries as $entry) {
            $name = $entry['name'];
            $content = $entry['content'];
            $compressed = gzcompress($content);
            $compressed = substr($compressed, 2, -4);
            $crc = crc32($content);
            $compressedLength = strlen($compressed);
            $uncompressedLength = strlen($content);
            $dosTime = $this->dosTime();
            $dosDate = $this->dosDate();

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                8,
                $dosTime,
                $dosDate,
                $crc,
                $compressedLength,
                $uncompressedLength,
                strlen($name),
                0
            );

            $data .= $localHeader . $name . $compressed;

            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                8,
                $dosTime,
                $dosDate,
                $crc,
                $compressedLength,
                $uncompressedLength,
                strlen($name),
                0,
                0,
                0,
                0,
                32,
                $offset
            ) . $name;

            $offset = strlen($data);
            $count++;
        }

        $footer = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            strlen($centralDirectory),
            strlen($data),
            0
        );

        return $data . $centralDirectory . $footer;
    }

    private function dosTime(): int
    {
        return ((int) date('H') << 11) | ((int) date('i') << 5) | ((int) floor((int) date('s') / 2));
    }

    private function dosDate(): int
    {
        return (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');
    }
}

