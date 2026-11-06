<?php

namespace Tests\Unit;

use App\Http\Requests\ParentGoalMessageRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ParentGoalMessageRequestTest extends TestCase
{
    private function validate(array $files, array $input = []): \Illuminate\Validation\Validator
    {
        $request = ParentGoalMessageRequest::create('/api/goals/parent-details/1/messages', 'POST', $input, [], $files);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
    }

    public function test_requires_an_attachment(): void
    {
        $validator = $this->validate([], ['content' => 'only text']);

        $this->assertTrue($validator->errors()->has('file'));
    }

    public function test_accepts_a_single_document_with_caption(): void
    {
        $validator = $this->validate(['file' => UploadedFile::fake()->create('report.pdf', 200, 'application/pdf')], ['content' => 'note']);

        $this->assertFalse($validator->errors()->any());
    }

    public function test_rejects_more_than_one_attachment(): void
    {
        $validator = $this->validate([
            'file' => UploadedFile::fake()->create('report.pdf', 200, 'application/pdf'),
            'video' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ]);

        $this->assertTrue($validator->errors()->has('file'));
    }

    public function test_attachment_field_reports_the_uploaded_type(): void
    {
        $request = ParentGoalMessageRequest::create('/x', 'POST', [], [], [
            'video' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ]);

        $this->assertSame('video', $request->attachmentField());
    }

    public function test_accepts_a_21_mb_video(): void
    {
        $validator = $this->validate(['video' => UploadedFile::fake()->create('clip.mp4', 21 * 1024, 'video/mp4')]);

        $this->assertFalse($validator->errors()->any());
    }

    public function test_accepts_a_video_at_the_limit(): void
    {
        $validator = $this->validate([
            'video' => UploadedFile::fake()->create('clip.mov', ParentGoalMessageRequest::MAX_KILOBYTES['video'], 'video/quicktime'),
        ]);

        $this->assertFalse($validator->errors()->any());
    }

    public function test_rejects_a_video_above_the_limit_with_a_size_message(): void
    {
        $validator = $this->validate([
            'video' => UploadedFile::fake()->create('clip.mp4', ParentGoalMessageRequest::MAX_KILOBYTES['video'] + 1, 'video/mp4'),
        ]);

        $this->assertSame(
            __('validation.parent_goal_details.too_large', ['mb' => 200]),
            $validator->errors()->first('video')
        );
    }

    public function test_every_attachment_type_allows_200_mb(): void
    {
        foreach (ParentGoalMessageRequest::ATTACHMENT_FIELDS as $field) {
            $this->assertSame(200 * 1024, ParentGoalMessageRequest::MAX_KILOBYTES[$field], $field);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: int}>
     */
    public static function sizesWithinTheLimit(): array
    {
        $cases = [];
        foreach ([5, 21, 50, 100, 199, 200] as $mb) {
            $cases["{$mb} MB video"] = ['video', 'clip.mp4', 'video/mp4', $mb];
            $cases["{$mb} MB document"] = ['file', 'report.pdf', 'application/pdf', $mb];
            $cases["{$mb} MB image"] = ['image', 'photo.jpg', 'image/jpeg', $mb];
        }

        return $cases;
    }

    /**
     * @dataProvider sizesWithinTheLimit
     */
    public function test_accepts_files_up_to_200_mb(string $field, string $name, string $mime, int $mb): void
    {
        $validator = $this->validate([$field => UploadedFile::fake()->create($name, $mb * 1024, $mime)]);

        $this->assertFalse($validator->errors()->any(), (string) $validator->errors()->first());
    }

    public function test_rejects_every_type_just_above_200_mb(): void
    {
        foreach (['video' => ['clip.mp4', 'video/mp4'], 'file' => ['report.pdf', 'application/pdf'], 'image' => ['photo.png', 'image/png']] as $field => [$name, $mime]) {
            $validator = $this->validate([$field => UploadedFile::fake()->create($name, 200 * 1024 + 1, $mime)]);

            $this->assertSame(
                __('validation.parent_goal_details.too_large', ['mb' => 200]),
                $validator->errors()->first($field),
                $field
            );
        }
    }

    public function test_accepts_android_3gp_video(): void
    {
        $validator = $this->validate(['video' => UploadedFile::fake()->create('VID_0001.3gp', 2048, 'video/3gpp')]);

        $this->assertFalse($validator->errors()->any(), (string) $validator->errors()->first());
    }

    public function test_rejects_a_file_name_without_extension(): void
    {
        $validator = $this->validate(['video' => UploadedFile::fake()->create('1000012345', 2048, 'video/mp4')]);

        $this->assertTrue($validator->errors()->has('video'));
    }

    public function test_rejects_unsupported_document_type(): void
    {
        $validator = $this->validate(['file' => UploadedFile::fake()->create('page.html', 10, 'text/html')]);

        $this->assertTrue($validator->errors()->has('file'));
    }

    public function test_rejects_php_disguised_as_pdf(): void
    {
        $validator = $this->validate(['file' => UploadedFile::fake()->create('shell.php', 10, 'application/pdf')]);

        $this->assertTrue($validator->errors()->has('file'));
    }

    public function test_rejects_valid_image_content_with_unsafe_extension(): void
    {
        $validator = $this->validate(['image' => UploadedFile::fake()->create('photo.html', 100, 'image/jpeg')]);

        $this->assertTrue($validator->errors()->has('image'));
    }

    public function test_rejects_non_video_content_in_video_field(): void
    {
        $validator = $this->validate(['video' => UploadedFile::fake()->create('clip.mp4', 100, 'application/pdf')]);

        $this->assertTrue($validator->errors()->has('video'));
    }

    public function test_server_side_size_rejection_reports_too_large(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pgm');
        $file = new UploadedFile($path, 'big.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, true);

        $validator = $this->validate(['video' => $file]);

        $this->assertSame(
            __('validation.parent_goal_details.too_large', ['mb' => 200]),
            $validator->errors()->first('video')
        );
        @unlink($path);
    }

    public function test_partial_upload_reports_interruption(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pgm');
        $file = new UploadedFile($path, 'clip.mp4', 'video/mp4', UPLOAD_ERR_PARTIAL, true);

        $validator = $this->validate(['video' => $file]);

        $this->assertSame(
            __('validation.parent_goal_details.upload_interrupted'),
            $validator->errors()->first('video')
        );
        @unlink($path);
    }

    public function test_client_rules_match_server_limits(): void
    {
        $rules = ParentGoalMessageRequest::clientRules();

        $this->assertSame(200, $rules['image']['max_mb']);
        $this->assertSame(200, $rules['video']['max_mb']);
        $this->assertSame(200, $rules['file']['max_mb']);
        $this->assertContains('mov', $rules['video']['extensions']);
        $this->assertContains('3gp', $rules['video']['extensions']);
        $this->assertNotContains('php', $rules['file']['extensions']);
    }
}
