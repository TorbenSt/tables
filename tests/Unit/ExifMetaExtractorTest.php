<?php

namespace Tests\Unit;

use App\Services\Sun\ExifMetaExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExifMetaExtractorTest extends TestCase
{
    #[Test]
    public function it_returns_empty_array_for_jpeg_without_exif(): void
    {
        $path = sys_get_temp_dir().'/tables-no-exif.jpg';
        $im = imagecreatetruecolor(40, 30);
        imagejpeg($im, $path, 80);
        imagedestroy($im);

        $result = (new ExifMetaExtractor)->fromPath($path);
        @unlink($path);

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_missing_file(): void
    {
        $this->assertSame([], (new ExifMetaExtractor)->fromPath('/tmp/does-not-exist-tables.jpg'));
    }
}
