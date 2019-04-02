<?php
declare(strict_types=1);

namespace Firehed\Console;

use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass Firehed\Console\Deploy
 * @covers ::<protected>
 * @covers ::<private>
 */
class DeployTest extends \PHPUnit\Framework\TestCase
{
    private $branch;

    public function setUp()
    {
        $this->branch = trim(`git rev-parse --abbrev-ref HEAD`);
    }

    /** @covers ::__construct */
    public function testExecuteUsesHead()
    {
        $kubes = $this->createMock(Deploy\Kubectl::class);
        $kubes->expects($this->atLeastOnce())
            ->method('deploy')
            ->willReturnCallback(function ($tag) {
                $this->assertTrue(
                    (bool) preg_match('#^[0-9a-f]{40}$#', $tag),
                    'Parameter to deploy should have been a valid git commit hash'
                );
            });
        $command = new Deploy($kubes);
        $tester = new CommandTester($command);
        $tester->execute(['revision' => $this->branch]);
    }

    /** @covers ::before */
    public function testBefore()
    {
        $count = 0;
        $hasRun = false;
        $before = function ($hash, $rev, $isDryRun) use (&$count, &$hasRun) {
            $this->assertFalse($hasRun, 'Should not have run yet');
            $this->assertFalse($isDryRun);
            $this->assertSame($this->branch, $rev);
            $this->assertSame(40, strlen($hash));
            $count++;
        };

        $kubes = $this->createMock(Deploy\Kubectl::class);
        $kubes->expects($this->once())
            ->method('deploy')
            ->willReturnCallback(function ($hash) use (&$hasRun) {
                $hasRun = true;
            });
        $command = new Deploy($kubes);
        $command->before($before);
        $command->before($before);
        $tester = new CommandTester($command);
        $tester->execute(['revision' => $this->branch]);
        $this->assertSame(2, $count, 'Both before hooks should have been fired');
        $this->assertTrue($hasRun, 'Deploy should have run');
    }

    /** @covers ::after */
    public function testAfter()
    {
        $count = 0;
        $hasRun = false;
        $after = function ($hash, $rev, $isDryRun) use (&$count, &$hasRun) {
            $this->assertTrue($hasRun, 'Should have run already');
            $this->assertFalse($isDryRun);
            $this->assertSame($this->branch, $rev);
            $this->assertSame(40, strlen($hash));
            $count++;
        };

        $kubes = $this->createMock(Deploy\Kubectl::class);
        $kubes->expects($this->once())
            ->method('deploy')
            ->willReturnCallback(function ($hash) use (&$hasRun) {
                $hasRun = true;
            });
        $command = new Deploy($kubes);
        $command->after($after);
        $command->after($after);
        $tester = new CommandTester($command);
        $tester->execute(['revision' => $this->branch]);
        $this->assertSame(2, $count, 'Both after hooks should have been fired');
        $this->assertTrue($hasRun, 'Deploy should have run');
    }
}
