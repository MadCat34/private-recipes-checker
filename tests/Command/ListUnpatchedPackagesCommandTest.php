<?php

namespace App\Tests\Command;

use App\Command\ListUnpatchedPackagesCommand;
use App\Tests\Support\FakeVcsProvider;
use App\Tests\Support\RecordingErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class ListUnpatchedPackagesCommandTest extends TestCase
{
    private string $fixtureDir;
    private string $originalCwd;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->fixtureDir = sys_get_temp_dir().'/list-unpatched-test-'.uniqid();
        $this->filesystem->mkdir([
            $this->fixtureDir.'/acme/private-bundle',
            $this->fixtureDir.'/acme/other-bundle',
            $this->fixtureDir.'/acme/untouched-bundle',
        ]);
        $this->originalCwd = getcwd();
        chdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->filesystem->remove($this->fixtureDir);
    }

    public function testListsOnlyPackagesNotTouchedByTheChangedFiles(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->changedFiles = [
            'acme/private-bundle/1.0/manifest.json',
            'acme/other-bundle/2.0/config/packages/other.yaml',
        ];

        $tester = new CommandTester(new ListUnpatchedPackagesCommand($vcs));
        $tester->execute([]);

        $this->assertSame("acme/untouched-bundle\n", $tester->getDisplay());
    }

    public function testAllPackagesAreListedWhenNothingChanged(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->changedFiles = [];

        $tester = new CommandTester(new ListUnpatchedPackagesCommand($vcs));
        $tester->execute([]);

        $lines = array_filter(explode("\n", $tester->getDisplay()));
        sort($lines);
        $this->assertSame(['acme/other-bundle', 'acme/private-bundle', 'acme/untouched-bundle'], $lines);
    }
}
