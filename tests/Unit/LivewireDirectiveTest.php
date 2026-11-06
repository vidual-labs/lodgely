<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A Livewire action directive without an expression (`wire:click.stop` used
 * just to stop propagation) compiles to the Alpine expression `$wire.` and
 * throws "Unexpected token '}'" in the browser on every click. The inbox's
 * row checkbox cell did exactly that. Stopping propagation is Alpine's job:
 * `x-on:click.stop`.
 */
class LivewireDirectiveTest extends TestCase
{
    public function test_no_livewire_action_directive_is_left_without_an_expression(): void
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // Blade comments explain these directives in prose; only real tags count.
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file->getPathname()));

            preg_match_all('/<[a-zA-Z][^<>]*?\swire:(?:click|submit|change|keydown|keyup|input|blur|focus)(?:\.[\w.-]+)*(?=[\s>\/])(?!\s*=)[^<>]*>/s', $source, $m);
            foreach ($m[0] as $tag) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1).': '.preg_replace('/\s+/', ' ', $tag);
            }
        }

        $this->assertSame([], $offenders, "Livewire directives without an expression:\n".implode("\n", $offenders));
    }
}
