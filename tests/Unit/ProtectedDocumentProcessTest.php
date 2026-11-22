<?php

namespace Tests\Unit;

use App\Services\ProtectedDocumentService;
use ReflectionMethod;
use Tests\TestCase;

class ProtectedDocumentProcessTest extends TestCase
{
    public function test_converter_retains_system_paths_without_request_variables_or_credentials(): void
    {
        $server = $_SERVER;
        $environment = $_ENV;
        $previousSecret = getenv('JK_DOCUMENT_PROCESS_TEST_SECRET');
        $expectedPath = getenv('PATH');
        $this->assertNotFalse($expectedPath);

        try {
            putenv('JK_DOCUMENT_PROCESS_TEST_SECRET=private-test-value');
            // Reproduce an HTTP SAPI with request-only server variables. Symfony
            // must not infer the converter's OS environment from these keys.
            $_SERVER = ['JK_DOCUMENT_PROCESS_TEST_SECRET' => 'private-test-value', 'HTTP_AUTHORIZATION' => 'Bearer private-test-value'];
            $_ENV = ['JK_DOCUMENT_PROCESS_TEST_SECRET' => 'private-test-value'];
            $output = (new ReflectionMethod(ProtectedDocumentService::class, 'run'))->invoke(
                new ProtectedDocumentService,
                [PHP_BINARY, '-r', 'echo json_encode([getenv("PATH"), getenv("JK_DOCUMENT_PROCESS_TEST_SECRET"), getenv("HTTP_AUTHORIZATION")]);'],
                15,
            );

            $this->assertSame([$expectedPath, false, false], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
        } finally {
            $_SERVER = $server;
            $_ENV = $environment;
            putenv($previousSecret === false ? 'JK_DOCUMENT_PROCESS_TEST_SECRET' : 'JK_DOCUMENT_PROCESS_TEST_SECRET='.$previousSecret);
        }
    }
}
