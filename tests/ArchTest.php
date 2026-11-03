<?php

namespace Fayne\ShoppingCart\Tests;

use PHPUnit\Framework\TestCase;

class ArchTest extends TestCase
{
    public function test_package_source_has_no_debug_calls(): void
    {
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../src'));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $tokens = token_get_all(file_get_contents($file->getPathname()));
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                    || !in_array(strtolower(ltrim($token[1], '\\')), ['dd', 'dump', 'ray'], true)) {
                    continue;
                }
                do {
                    $next = $tokens[++$index] ?? null;
                } while (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
                if ($next === '(') {
                    $violations[] = $file->getPathname() . ':' . $token[2];
                }
            }
        }
        $this->assertSame([], $violations);
    }
}
