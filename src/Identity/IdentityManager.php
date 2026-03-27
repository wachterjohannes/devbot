<?php

declare(strict_types=1);

namespace App\Identity;

use App\Identity\Model\HumanProfile;
use App\Identity\Model\Identity;
use App\Identity\Model\Soul;

/**
 * Loads and manages SOUL, IDENTITY, and HUMAN profile files.
 * All identity files are markdown — human-readable and git-versionable.
 */
final class IdentityManager
{
    public function __construct(
        private readonly string $soulFile,
        private readonly string $identityFile,
        private readonly string $humansDir,
    ) {
    }

    public function loadSoul(): Soul
    {
        $content = $this->readFile($this->soulFile);

        return new Soul($content);
    }

    public function loadIdentity(): Identity
    {
        $content = $this->readFile($this->identityFile);

        return new Identity($content);
    }

    /**
     * @return list<HumanProfile>
     */
    public function loadHumanProfiles(): array
    {
        $profiles = [];
        $dir = $this->humansDir;

        if (!is_dir($dir)) {
            return [];
        }

        foreach (glob($dir . '/*.md') as $file) {
            $basename = basename($file, '.md');
            if ($basename === '_template') {
                continue;
            }

            $profiles[] = new HumanProfile(
                name: $basename,
                content: $this->readFile($file),
            );
        }

        return $profiles;
    }

    private function readFile(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        return file_get_contents($path) ?: '';
    }
}
