<?php

namespace App\Tests\ErrorReporter;

use App\ErrorReporter\GitLabErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

class GitLabErrorReporterTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir().'/gitlab-error-reporter-test-'.uniqid();
        (new Filesystem())->mkdir($this->outputDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->outputDir);
    }

    public function testReportErrorPrintsPlainMessage(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitLabErrorReporter($output, $this->outputDir);

        $reporter->reportError('Bad key', 'manifest.json', 12);

        $this->assertSame("manifest.json:12: Bad key\n", $output->fetch());
    }

    public function testFlushWritesCodeQualityReportNamedAfterTheCommand(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitLabErrorReporter($output, $this->outputDir);

        $reporter->reportError('Bad key', 'manifest.json', 12);
        $reporter->reportError('Another problem', 'other.yaml');
        $reporter->flush('lint:manifests');

        $path = $this->outputDir.'/gl-code-quality-report-lint-manifests.json';
        $this->assertFileExists($path);

        $report = json_decode(file_get_contents($path), true);
        $this->assertCount(2, $report);
        $this->assertSame('Bad key', $report[0]['description']);
        $this->assertSame('manifest.json', $report[0]['location']['path']);
        $this->assertSame(12, $report[0]['location']['lines']['begin']);
        $this->assertSame('Another problem', $report[1]['description']);
        $this->assertSame('other.yaml', $report[1]['location']['path']);
        $this->assertSame(1, $report[1]['location']['lines']['begin']);
        $this->assertArrayHasKey('fingerprint', $report[0]);
    }

    public function testFlushWithNoErrorsWritesAnEmptyArray(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitLabErrorReporter($output, $this->outputDir);

        $reporter->flush('lint:yaml');

        $path = $this->outputDir.'/gl-code-quality-report-lint-yaml.json';
        $this->assertSame("[]\n", file_get_contents($path));
    }
}
