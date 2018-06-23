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
        $tester->execute([]);
    }
}
