<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Security;

use Narrowspark\Automatic\Security\Audit;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AuditTest extends TestCase
{
    /**
     * @var \Narrowspark\Automatic\Security\Audit
     */
    private $audit;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->audit = new Audit(__DIR__);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        $dir = __DIR__ . \DIRECTORY_SEPARATOR . 'narrowspark' . \DIRECTORY_SEPARATOR . 'automatic';

        @\unlink($dir . \DIRECTORY_SEPARATOR . 'security-advisories.json');
        @\unlink($dir . \DIRECTORY_SEPARATOR . 'security-advisories-sha');
        @\rmdir($dir);
    }

    public function testCheckLockWithSymfony252(): void
    {
        [$vulnerabilitiesCount, $vulnerabilities] = $this->audit->checkLock(
            \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock'
        );

        static::assertSame(1, $vulnerabilitiesCount);
    }
}
