<?php

namespace Tests\Unit;

use App\Rules\HttpUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HttpUrlRuleTest extends TestCase
{
    private function fails(mixed $value): bool
    {
        return Validator::make(['url' => $value], ['url' => [new HttpUrl()]])->fails();
    }

    public function test_http_and_https_urls_pass(): void
    {
        $this->assertFalse($this->fails('https://forms.example.com'));
        $this->assertFalse($this->fails('http://localhost:11434'));
        $this->assertFalse($this->fails('https://api.openai.com/v1'));
    }

    /**
     * Blank means "use the configured default" on both fields this rule
     * guards, so it must pass. Laravel's built-in url rule rejects an empty
     * string even next to `nullable`, which is why this rule exists at all.
     */
    public function test_blank_values_pass_so_defaults_still_work(): void
    {
        $this->assertFalse($this->fails(''));
        $this->assertFalse($this->fails(null));
        $this->assertFalse($this->fails('   '));
    }

    public function test_non_http_schemes_are_rejected(): void
    {
        $this->assertTrue($this->fails('ftp://files.example.com'));
        $this->assertTrue($this->fails('file:///etc/passwd'));
        $this->assertTrue($this->fails('javascript:alert(1)'));
    }

    public function test_garbage_is_rejected(): void
    {
        $this->assertTrue($this->fails('not a url'));
        $this->assertTrue($this->fails('example.com'));
    }

    public function test_cleartext_warning_fires_for_remote_http_hosts(): void
    {
        $this->assertTrue(HttpUrl::isCleartextToRemoteHost('http://forms.example.com'));
        $this->assertTrue(HttpUrl::isCleartextToRemoteHost('http://203.0.113.10:8080'));
    }

    /**
     * The warning has to stay quiet for local and LAN hosts, otherwise it
     * fires on every self-hosted install and gets trained away.
     */
    public function test_cleartext_warning_stays_quiet_for_local_and_encrypted_hosts(): void
    {
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost('https://forms.example.com'));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost('http://localhost:11434'));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost('http://127.0.0.1:3000'));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost('http://192.168.1.50'));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost('http://10.0.0.4:8080'));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost('http://my-nas.local'));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost(''));
        $this->assertFalse(HttpUrl::isCleartextToRemoteHost(null));
    }
}
