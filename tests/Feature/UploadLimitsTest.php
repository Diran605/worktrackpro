<?php

namespace Tests\Feature;

use App\Support\UploadLimits;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * When PHP refuses a file (the server's upload limit), people are told why.
 */
class UploadLimitsTest extends TestCase
{
    public function test_sizes_are_read_from_php_settings(): void
    {
        $this->assertSame(2 * 1048576, UploadLimits::bytes('2M'));
        $this->assertSame(512 * 1024, UploadLimits::bytes('512K'));
        $this->assertSame(1073741824, UploadLimits::bytes('1G'));
        $this->assertSame(0, UploadLimits::bytes(''));
    }

    public function test_a_refused_upload_explains_the_server_limit(): void
    {
        $file = new \Illuminate\Http\UploadedFile(__FILE__, 'manual.docx', null, UPLOAD_ERR_INI_SIZE, true);

        $message = Validator::make(['file' => $file], ['file' => ['required', 'file']])->errors()->first('file');

        $this->assertStringContainsString('too large for the server', $message);
        $this->assertStringContainsString(UploadLimits::maxLabel(), $message);
    }
}
