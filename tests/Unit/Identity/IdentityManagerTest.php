<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\IdentityManager;
use PHPUnit\Framework\TestCase;

final class IdentityManagerTest extends TestCase
{
    public function testLoadSoul(): void
    {
        $manager = new IdentityManager(
            soulFile: __DIR__ . '/../../../identity/SOUL.md',
            identityFile: __DIR__ . '/../../../identity/IDENTITY.md',
            humansDir: __DIR__ . '/../../../identity/humans',
        );

        $soul = $manager->loadSoul();
        self::assertNotEmpty($soul->content);
        self::assertStringContainsString('SOUL', $soul->content);
    }

    public function testLoadIdentity(): void
    {
        $manager = new IdentityManager(
            soulFile: __DIR__ . '/../../../identity/SOUL.md',
            identityFile: __DIR__ . '/../../../identity/IDENTITY.md',
            humansDir: __DIR__ . '/../../../identity/humans',
        );

        $identity = $manager->loadIdentity();
        self::assertNotEmpty($identity->content);
        self::assertStringContainsString('IDENTITY', $identity->content);
    }

    public function testLoadHumanProfiles(): void
    {
        $manager = new IdentityManager(
            soulFile: __DIR__ . '/../../../identity/SOUL.md',
            identityFile: __DIR__ . '/../../../identity/IDENTITY.md',
            humansDir: __DIR__ . '/../../../identity/humans',
        );

        $profiles = $manager->loadHumanProfiles();
        self::assertNotEmpty($profiles);
        self::assertSame('johannes', $profiles[0]->name);
        self::assertStringContainsString('Johannes Wachter', $profiles[0]->content);
    }

    public function testLoadMissingFilesGracefully(): void
    {
        $manager = new IdentityManager(
            soulFile: '/nonexistent/SOUL.md',
            identityFile: '/nonexistent/IDENTITY.md',
            humansDir: '/nonexistent/humans',
        );

        self::assertSame('', $manager->loadSoul()->content);
        self::assertSame('', $manager->loadIdentity()->content);
        self::assertSame([], $manager->loadHumanProfiles());
    }
}
