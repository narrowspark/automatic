<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\LegacyTagsManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class LegacyTagsManagerTest extends MockeryTestCase
{
    /**
     * @var array
     */
    private $downloadFileList;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Narrowspark\Automatic\LegacyTagsManager
     */
    private $tagsManger;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'Packagist';

        $this->downloadFileList = [
            'cakephp$cakephp'              => $pPath . \DIRECTORY_SEPARATOR . 'provider-cakephp$cakephp.json',
            'codeigniter$framework'        => $pPath . \DIRECTORY_SEPARATOR . 'provider-codeigniter$framework.json',
            'symfony$security-guard'       => $pPath . \DIRECTORY_SEPARATOR . 'provider-symfony$security-guard.json',
            'symfony$symfony'              => $pPath . \DIRECTORY_SEPARATOR . 'provider-symfony$symfony.json',
            'zendframework$zend-diactoros' => $pPath . \DIRECTORY_SEPARATOR . 'provider-zendframework$zend-diactoros.json',
        ];

        $this->ioMock     = $this->mock(IOInterface::class);
        $this->tagsManger = new LegacyTagsManager($this->ioMock);
    }

    public function testHasProvider(): void
    {
        $count = 0;

        $this->tagsManger->addConstraint('symfony/security-guard', '>=4.1');

        foreach ($this->downloadFileList as $file) {
            if ($this->tagsManger->hasProvider($file)) {
                $count++;
            }
        }

        static::assertSame(2, $count);
    }

    public function testRemoveLegacyTagsWithoutDataPackages(): void
    {
        static::assertSame([], $this->tagsManger->removeLegacyTags([]));
    }

    public function testRemoveLegacyTagsWithSymfony(): void
    {
        $this->tagsManger->addConstraint('symfony/symfony', '>=3.4');

        $originalData = \json_decode(\file_get_contents($this->downloadFileList['symfony$symfony']), true);

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', 'symfony/symfony', '>=3.4'));

        $data = $this->tagsManger->removeLegacyTags($originalData);

        static::assertNotSame($originalData['packages'], $data['packages']);
    }

    public function testRemoveLegacyTagsSkipIfNoProviderFound(): void
    {
        $originalData = \json_decode(\file_get_contents($this->downloadFileList['codeigniter$framework']), true);

        static::assertSame($originalData, $this->tagsManger->removeLegacyTags($originalData));
    }

    public function testRemoveLegacyTagsWithCakePHP(): void
    {
        $originalData = \json_decode(\file_get_contents($this->downloadFileList['cakephp$cakephp']), true);

        $this->tagsManger->addConstraint('cakephp/cakephp', '>=3.5');

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', 'cakephp/cakephp', '>=3.5'));

        $data = $this->tagsManger->removeLegacyTags($originalData);

        static::assertNotSame($originalData['packages'], $data['packages']);
    }
}
