<?php

namespace PrestaFlow\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Command\ExecuteSuite;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Two runs on the same machine must not share, nor tear down, each other's
 * browser.
 *
 * The keepAlive browser is found again through a socket file. That file used
 * to sit at one fixed path per machine, so every run read and wrote the same
 * one, and every run ended by closing the browser the file named and deleting
 * it. A run that finished — even a browser-free one — closed the Chrome another
 * run was driving, which then failed with "The page was closed and is not
 * available anymore".
 *
 * No browser is needed: the socket file points to a port nothing listens on,
 * so releaseBrowser() cannot connect and only its file handling is exercised.
 */
final class BrowserOwnershipTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        TestsSuite::scopeBrowserFilesTo(null);
    }

    public function testARunThatEndsLeavesTheBrowserOfAnotherRunAlone(): void
    {
        $first = new ExecuteSuite();
        $first->beginRun();
        $firstSocketFile = TestsSuite::getFilePath('.browser');
        $this->files[] = $firstSocketFile;
        file_put_contents($firstSocketFile, 'ws://127.0.0.1:1/devtools/browser/first-run');

        // A second run starts and finishes while the first is still driving
        // its browser (in real life: another process, same temp dir).
        $second = new ExecuteSuite();
        $second->beginRun();
        $this->files[] = TestsSuite::getFilePath('.browser');
        $second->releaseBrowser();

        $this->assertFileExists(
            $firstSocketFile,
            'the second run deleted the socket file of the first one'
        );
    }

    public function testEachRunGetsItsOwnSocketFile(): void
    {
        $first = new ExecuteSuite();
        $first->beginRun();
        $firstSocketFile = TestsSuite::getFilePath('.browser');

        $second = new ExecuteSuite();
        $second->beginRun();

        $this->assertNotSame($firstSocketFile, TestsSuite::getFilePath('.browser'));
    }

    public function testARunReleasesItsOwnSocketFile(): void
    {
        $run = new ExecuteSuite();
        $run->beginRun();
        $socketFile = TestsSuite::getFilePath('.browser');
        $this->files[] = $socketFile;
        file_put_contents($socketFile, 'ws://127.0.0.1:1/devtools/browser/own-run');

        $run->releaseBrowser();

        $this->assertFileDoesNotExist($socketFile);
    }

    public function testWithoutARunTheSharedPathIsUnchanged(): void
    {
        TestsSuite::scopeBrowserFilesTo(null);

        $this->assertSame(
            sys_get_temp_dir() . '/prestaflow-.browser',
            TestsSuite::getFilePath('.browser')
        );
    }
}
