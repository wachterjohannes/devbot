<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tool;

use App\Tool\Git\GitCommitTool;
use App\Tool\Git\GitStatusTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: git tools against a real temp git repo.
 */
final class GitToolsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_git_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);

        // Init a git repo
        $this->git('init');
        $this->git('config user.email "test@devbot.local"');
        $this->git('config user.name "DevBot Test"');

        // Create initial commit
        file_put_contents($this->tmpDir . '/README.md', '# Test');
        $this->git('add .');
        $this->git('commit -m "init"');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testGitStatus(): void
    {
        $tool = new GitStatusTool($this->tmpDir);

        $result = $tool('status');
        self::assertSame(0, $result['exit_code']);
        self::assertSame('', $result['output']); // Clean repo
    }

    public function testGitStatusWithChanges(): void
    {
        file_put_contents($this->tmpDir . '/new.txt', 'hello');

        $tool = new GitStatusTool($this->tmpDir);
        $result = $tool('status');

        self::assertStringContainsString('new.txt', $result['output']);
    }

    public function testGitLog(): void
    {
        $tool = new GitStatusTool($this->tmpDir);
        $result = $tool('log', 5);

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString('init', $result['output']);
    }

    public function testGitBranch(): void
    {
        $tool = new GitStatusTool($this->tmpDir);
        $result = $tool('branch');

        self::assertSame(0, $result['exit_code']);
        self::assertNotEmpty($result['output']);
    }

    public function testGitCommit(): void
    {
        file_put_contents($this->tmpDir . '/feature.txt', 'new feature');

        $tool = new GitCommitTool($this->tmpDir);
        $result = $tool('Add feature', ['feature.txt']);

        self::assertTrue($result['committed']);
        self::assertStringContainsString('Add feature', $result['output']);
    }

    public function testGitCommitAllTracked(): void
    {
        // Modify tracked file
        file_put_contents($this->tmpDir . '/README.md', '# Updated');

        $tool = new GitCommitTool($this->tmpDir);
        $result = $tool('Update readme');

        self::assertTrue($result['committed']);
    }

    public function testGitCommitNothingToCommit(): void
    {
        $tool = new GitCommitTool($this->tmpDir);
        $result = $tool('Empty commit');

        self::assertFalse($result['committed']);
    }

    private function git(string $command): void
    {
        exec("cd {$this->tmpDir} && git {$command} 2>&1");
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
