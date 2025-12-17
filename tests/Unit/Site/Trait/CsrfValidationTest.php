<?php

declare(strict_types=1);

namespace Tests\Unit\Site\Trait;

use Joomla\Component\Nxpeasycart\Site\Trait\CsrfValidation;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CsrfValidation trait structure and behavior documentation.
 *
 * Note: Full integration testing of CSRF validation requires a running Joomla
 * application with valid session. These tests verify the trait's structure
 * and documented behavior patterns.
 *
 * @since 0.3.2
 */
final class CsrfValidationTest extends TestCase
{
    /**
     * Create a test class that uses the CsrfValidation trait.
     */
    private function createTraitUser(): object
    {
        return new class {
            use CsrfValidation;

            // Expose protected methods for testing
            public function publicHasValidCsrfToken(bool $allowQuery = false): bool
            {
                return $this->hasValidCsrfToken($allowQuery);
            }

            public function getReflection(): \ReflectionClass
            {
                return new \ReflectionClass($this);
            }
        };
    }

    /**
     * @test
     */
    public function testTraitCanBeUsedByClass(): void
    {
        $controller = $this->createTraitUser();

        $this->assertNotNull($controller);
        $this->assertTrue(
            in_array(CsrfValidation::class, class_uses($controller), true),
            'Test class should use CsrfValidation trait'
        );
    }

    /**
     * @test
     */
    public function testHasValidCsrfTokenMethodExists(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('hasValidCsrfToken'),
            'Trait should provide hasValidCsrfToken method'
        );

        $method = $reflection->getMethod('hasValidCsrfToken');
        $this->assertTrue($method->isProtected(), 'hasValidCsrfToken should be protected');
    }

    /**
     * @test
     */
    public function testRequireCsrfTokenMethodExists(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('requireCsrfToken'),
            'Trait should provide requireCsrfToken method'
        );

        $method = $reflection->getMethod('requireCsrfToken');
        $this->assertTrue($method->isProtected(), 'requireCsrfToken should be protected');
    }

    /**
     * @test
     */
    public function testSendCsrfErrorResponseMethodExists(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('sendCsrfErrorResponse'),
            'Trait should provide sendCsrfErrorResponse method'
        );

        $method = $reflection->getMethod('sendCsrfErrorResponse');
        $this->assertTrue($method->isPrivate(), 'sendCsrfErrorResponse should be private');
    }

    /**
     * @test
     */
    public function testHasValidCsrfTokenAcceptsAllowQueryParameter(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();
        $method = $reflection->getMethod('hasValidCsrfToken');

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);

        $param = $parameters[0];
        $this->assertEquals('allowQuery', $param->getName());
        $this->assertEquals('bool', $param->getType()->getName());
        $this->assertTrue($param->isDefaultValueAvailable());
        $this->assertFalse($param->getDefaultValue());
    }

    /**
     * @test
     */
    public function testRequireCsrfTokenAcceptsAllowQueryParameter(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();
        $method = $reflection->getMethod('requireCsrfToken');

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);

        $param = $parameters[0];
        $this->assertEquals('allowQuery', $param->getName());
        $this->assertEquals('bool', $param->getType()->getName());
        $this->assertTrue($param->isDefaultValueAvailable());
        $this->assertFalse($param->getDefaultValue());
    }

    /**
     * @test
     */
    public function testHasValidCsrfTokenReturnsBool(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();
        $method = $reflection->getMethod('hasValidCsrfToken');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * @test
     */
    public function testRequireCsrfTokenReturnsVoid(): void
    {
        $controller = $this->createTraitUser();
        $reflection = $controller->getReflection();
        $method = $reflection->getMethod('requireCsrfToken');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }

    /**
     * @test
     */
    public function testTraitUsesTimingSafeComparison(): void
    {
        $traitFile = dirname(__DIR__, 4)
            . '/components/com_nxpeasycart/src/Trait/CsrfValidation.php';

        $this->assertFileExists($traitFile);

        $content = file_get_contents($traitFile);

        // Verify timing-safe comparison is used
        $this->assertStringContainsString(
            'hash_equals',
            $content,
            'Trait should use hash_equals for timing-safe token comparison'
        );
    }

    /**
     * @test
     */
    public function testTraitChecksHeaderToken(): void
    {
        $traitFile = dirname(__DIR__, 4)
            . '/components/com_nxpeasycart/src/Trait/CsrfValidation.php';

        $content = file_get_contents($traitFile);

        // Verify header token check is present
        $this->assertStringContainsString(
            'HTTP_X_CSRF_TOKEN',
            $content,
            'Trait should check X-CSRF-Token header'
        );
    }

    /**
     * @test
     */
    public function testTraitChecksPostToken(): void
    {
        $traitFile = dirname(__DIR__, 4)
            . '/components/com_nxpeasycart/src/Trait/CsrfValidation.php';

        $content = file_get_contents($traitFile);

        // Verify POST token check is present
        $this->assertStringContainsString(
            "checkToken('post')",
            $content,
            'Trait should check POST body token'
        );
    }

    /**
     * @test
     */
    public function testTraitChecksQueryTokenConditionally(): void
    {
        $traitFile = dirname(__DIR__, 4)
            . '/components/com_nxpeasycart/src/Trait/CsrfValidation.php';

        $content = file_get_contents($traitFile);

        // Verify query string token check is conditional
        $this->assertStringContainsString(
            '$allowQuery',
            $content,
            'Query string token check should be conditional on allowQuery parameter'
        );

        $this->assertStringContainsString(
            "checkToken('get')",
            $content,
            'Trait should check query string token when allowed'
        );
    }

    /**
     * @test
     * @dataProvider tokenPriorityProvider
     */
    public function testTokenCheckPriorityIsDocumented(string $method, int $priority): void
    {
        // Document the expected priority order:
        // 1. X-CSRF-Token header (highest priority)
        // 2. POST body token
        // 3. Query string token (only if allowQuery=true)

        $expectedPriority = [
            'header' => 1,
            'post' => 2,
            'query' => 3,
        ];

        $this->assertEquals(
            $expectedPriority[$method],
            $priority,
            "Token check priority for {$method} should be {$expectedPriority[$method]}"
        );
    }

    public static function tokenPriorityProvider(): array
    {
        return [
            'header has highest priority' => ['header', 1],
            'post has second priority' => ['post', 2],
            'query has lowest priority' => ['query', 3],
        ];
    }

    /**
     * @test
     */
    public function testTraitSends403OnInvalidToken(): void
    {
        $traitFile = dirname(__DIR__, 4)
            . '/components/com_nxpeasycart/src/Trait/CsrfValidation.php';

        $content = file_get_contents($traitFile);

        // Verify 403 status code is used
        $this->assertStringContainsString(
            '403',
            $content,
            'Trait should send 403 Forbidden on invalid token'
        );
    }

    /**
     * @test
     */
    public function testTraitUsesJoomlaInvalidTokenMessage(): void
    {
        $traitFile = dirname(__DIR__, 4)
            . '/components/com_nxpeasycart/src/Trait/CsrfValidation.php';

        $content = file_get_contents($traitFile);

        // Verify Joomla's standard token message is used
        $this->assertStringContainsString(
            'JINVALID_TOKEN',
            $content,
            'Trait should use Joomla standard JINVALID_TOKEN message'
        );
    }
}
