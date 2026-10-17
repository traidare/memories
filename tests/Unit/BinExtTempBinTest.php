<?php

declare(strict_types=1);

namespace OCA\Memories\Tests\Unit;

use OCA\Memories\Service\BinExt;
use OCA\Memories\Settings\SystemConfig;
use OCA\Memories\Tests\Injected;
use OCA\Memories\Tests\TestCase;
use OCA\Memories\Util;
use OCP\App\IAppManager;
use OCP\Config\IUserConfig;
use OCP\Encryption\IManager as EncryptionManager;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @internal
 *
 * @covers \OCA\Memories\Service\BinExt
 */
final class BinExtTempBinTest extends TestCase
{
    #[Injected]
    private BinExt $binExt;

    public function testGetTempBinCopiesAndCaches(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'memories-src-');
        file_put_contents($src, "#!/bin/sh\necho hi\n");
        $name = 'testbin-'.bin2hex(random_bytes(4));

        $target = $this->binExt->getTempBin($src, $name);
        self::assertFileExists($target);
        self::assertSame(file_get_contents($src), file_get_contents($target));
        self::assertTrue(is_executable($target));
        self::assertSame($target, $this->binExt->getTempBin($src, $name));

        unlink($src);
    }

    public function testGetTempBinNeedsNoWritability(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'memories-src-');
        file_put_contents($src, 'data');
        $name = 'testbin-'.bin2hex(random_bytes(4));

        $target = $this->binExt->getTempBin($src, $name);
        chmod($target, 0o555);

        self::assertSame($target, $this->binExt->getTempBin($src, $name));

        unlink($src);
    }

    public function testUnknownLibcSelectsSystemPerl(): void
    {
        $dir = sys_get_temp_dir().'/memories-ldd-'.bin2hex(random_bytes(8));
        mkdir($dir);
        $ldd = $dir.'/ldd';
        file_put_contents($ldd, "#!/bin/sh\nprintf 'unknown libc\\n'\n");
        chmod($ldd, 0o755);

        $oldPath = getenv('PATH');
        $oldLang = getenv('LANG');

        try {
            putenv('PATH='.$dir.':'.($oldPath ?: ''));
            self::assertNull(Util::getLibc());

            $values = [];
            $config = $this->createMock(IConfig::class);
            $config->method('getSystemValue')->willReturnCallback(
                static function (string $key, mixed $default) use (&$values): mixed {
                    return $values[$key] ?? $default;
                },
            );
            $config->expects(self::once())->method('setSystemValue')
                ->with('memories.exiftool_no_local', true)
                ->willReturnCallback(static function (string $key, mixed $value) use (&$values): void {
                    $values[$key] = $value;
                })
            ;

            $systemConfig = new SystemConfig(
                $config,
                self::createStub(IUserConfig::class),
                self::createStub(IRequest::class),
                self::createStub(IAppManager::class),
                self::createStub(IAppConfig::class),
                self::createStub(IUserSession::class),
                self::createStub(EncryptionManager::class),
            );
            $binExt = new BinExt($systemConfig, self::createStub(IClientService::class));

            self::assertFalse($binExt->detectExiftool());
            $command = $binExt->getExiftool();
            self::assertSame(['perl', realpath(__DIR__.'/../../bin-ext/exiftool/exiftool')], $command);
            self::assertSame(BinExt::EXIFTOOL_VER, trim((string) Util::execSafe([...$command, '-ver'], 3000)));
        } finally {
            false === $oldPath ? putenv('PATH') : putenv('PATH='.$oldPath);
            false === $oldLang ? putenv('LANG') : putenv('LANG='.$oldLang);
            unlink($ldd);
            rmdir($dir);
        }
    }
}
