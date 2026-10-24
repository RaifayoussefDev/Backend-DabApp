<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The container runs `php artisan config:cache`; once config is cached Laravel no longer loads .env,
 * so env() outside config/ only works by accident. Application code must read config() instead.
 */
class NoRuntimeEnvCallsTest extends TestCase
{
    public function test_app_code_does_not_call_env(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (token_get_all(file_get_contents($file->getPathname())) as $i => $token) {
                if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'env') {
                    $offenders[] = $file->getPathname() . ':' . $token[2];
                }
            }
        }

        $this->assertSame([], $offenders, "Use config() instead of env() in app/:\n" . implode("\n", $offenders));
    }
}
