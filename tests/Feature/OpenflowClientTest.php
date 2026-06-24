<?php

namespace Tests\Feature;

use App\Importers\Openflow\OpenflowClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenflowClientTest extends TestCase
{
    public function test_login_extracts_token_from_set_cookie(): void
    {
        Http::fake([
            'forms.example.com/api/auth/login' => Http::response(
                ['user' => ['id' => 'u1', 'email' => 'a@b.c']],
                200,
                ['Set-Cookie' => 'token=JWT123; Path=/; HttpOnly'],
            ),
        ]);

        $token = (new OpenflowClient())->login('https://forms.example.com', 'a@b.c', 'pw');

        $this->assertSame('JWT123', $token);
    }

    public function test_login_falls_back_to_body_token(): void
    {
        Http::fake([
            'forms.example.com/api/auth/login' => Http::response(['token' => 'BODYJWT'], 200),
        ]);

        $token = (new OpenflowClient())->login('https://forms.example.com', 'a@b.c', 'pw');

        $this->assertSame('BODYJWT', $token);
    }

    public function test_login_throws_friendly_message_on_401(): void
    {
        Http::fake([
            'forms.example.com/api/auth/login' => Http::response(['error' => 'Invalid credentials'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid email or password');

        (new OpenflowClient())->login('https://forms.example.com', 'a@b.c', 'bad');
    }

    public function test_base_url_trailing_slash_is_trimmed(): void
    {
        Http::fake([
            'forms.example.com/api/auth/login' => Http::response(
                ['user' => []], 200, ['Set-Cookie' => 'token=T; Path=/'],
            ),
        ]);

        (new OpenflowClient())->login('https://forms.example.com/', 'a@b.c', 'pw');

        Http::assertSent(fn ($request) => $request->url() === 'https://forms.example.com/api/auth/login');
    }

    public function test_list_forms_maps_response(): void
    {
        Http::fake([
            'forms.example.com/api/forms' => Http::response([
                'forms' => [
                    ['id' => 'F1', 'title' => 'Lead form', 'submission_count' => 12],
                    ['id' => '', 'title' => 'broken'], // skipped: no id
                ],
            ], 200),
        ]);

        $forms = (new OpenflowClient())->listForms('https://forms.example.com', 'TOKEN');

        $this->assertSame([
            ['id' => 'F1', 'title' => 'Lead form', 'submission_count' => 12],
        ], $forms);
    }

    public function test_form_fields_flattens_groups(): void
    {
        Http::fake([
            'forms.example.com/api/forms/FORM-1' => Http::response([
                'form' => [
                    'title' => 'Contact',
                    'steps' => [
                        ['id' => 'a', 'type' => 'email', 'label' => 'Email'],
                        ['id' => 'g', 'type' => 'group', 'fields' => [
                            ['id' => 'b', 'type' => 'short_text', 'label' => 'First'],
                            ['id' => 'c', 'type' => 'short_text', 'question' => 'Last'],
                        ]],
                        ['id' => 'd', 'type' => 'phone'], // no label → falls back to id
                    ],
                ],
            ], 200),
        ]);

        $form = (new OpenflowClient())->formFields('https://forms.example.com', 'TOKEN', 'FORM-1');

        $this->assertSame('Contact', $form['title']);
        $this->assertSame([
            ['id' => 'a', 'label' => 'Email', 'type' => 'email'],
            ['id' => 'b', 'label' => 'First', 'type' => 'short_text'],
            ['id' => 'c', 'label' => 'Last', 'type' => 'short_text'],
            ['id' => 'd', 'label' => 'd', 'type' => 'phone'],
        ], $form['fields']);
    }

    public function test_submissions_page_passes_pagination_and_parses(): void
    {
        Http::fake([
            'forms.example.com/api/submissions/FORM-1*' => Http::response([
                'submissions' => [
                    ['id' => 's1', 'data' => ['a' => 'x'], 'created_at' => '2026-06-20T00:00:00Z'],
                ],
                'total' => 1, 'page' => 2, 'limit' => 50,
            ], 200),
        ]);

        $page = (new OpenflowClient())->submissionsPage('https://forms.example.com', 'TOKEN', 'FORM-1', 2, 50);

        $this->assertSame(1, $page['total']);
        $this->assertSame('s1', $page['submissions'][0]['id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=2') && str_contains($request->url(), 'limit=50'));
    }

    public function test_request_throws_on_404_with_helpful_message(): void
    {
        Http::fake([
            'forms.example.com/api/submissions/MISSING*' => Http::response(['error' => 'Form not found'], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('check the form ID');

        (new OpenflowClient())->submissionsPage('https://forms.example.com', 'TOKEN', 'MISSING', 1, 100);
    }
}
