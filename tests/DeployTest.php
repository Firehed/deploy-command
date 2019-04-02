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
    /** @covers ::__construct */
    public function testExecuteUsesHead()
    {
        if (!`which git`) {
            $this->markTestSkipped('Git not available on this host');
        }
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
        $tester->execute(['revision' => 'head']);
    }

    /** @covers ::before */
    public function testBefore()
    {
        $count = 0;
        $hasRun = false;
        $before = function ($hash, $rev, $isDryRun) use (&$count, &$hasRun) {
            $this->assertFalse($hasRun, 'Should not have run yet');
            $this->assertFalse($isDryRun);
            $this->assertSame('head', $rev);
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
        $tester->execute(['revision' => 'head']);
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
            $this->assertSame('head', $rev);
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
        $tester->execute(['revision' => 'head']);
        $this->assertSame(2, $count, 'Both after hooks should have been fired');
        $this->assertTrue($hasRun, 'Deploy should have run');
    }
}
