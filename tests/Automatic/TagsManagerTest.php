<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\TagsManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class TagsManagerTest extends MockeryTestCase
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
     * @var \Narrowspark\Automatic\TagsManager
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
            'codeigniter$framework' => $pPath . \DIRECTORY_SEPARATOR . 'provider-codeigniter$framework.json',
            'symfony$security-guard' => $pPath . \DIRECTORY_SEPARATOR . 'provider-symfony$security-guard.json',
            'symfony$symfony' => $pPath . \DIRECTORY_SEPARATOR . 'provider-symfony$symfony.json',
            'zendframework$zend-diactoros' => $pPath . \DIRECTORY_SEPARATOR . 'provider-zendframework$zend-diactoros.json',
        ];

        $this->ioMock     = $this->mock(IOInterface::class);
        $this->tagsManger = new TagsManager($this->ioMock);
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
        $originalData = \json_decode(\file_get_contents($this->downloadFileList['symfony$symfony']), true);

        $data = $this->tagsManger->removeLegacyTags($originalData);

        static::assertNotSame($originalData['packages'], $data['packages']);
    }
}
