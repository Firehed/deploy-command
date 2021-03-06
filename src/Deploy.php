<?php
declare(strict_types=1);

namespace Firehed\Console;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function assert;
use function is_string;

/**
 * This is a fancy version of a bash two-liner, which reads or detects the
 * current git commit hash and insert it into an appropriate kubectl command.
 * Doing it this way allows for better error handling and avoids hardcoding the
 * image settings into the script.
 */
class Deploy extends Command
{
    private const ARG_REVISION = 'revision';
    const OPT_DRY_RUN = 'dry-run';

    /** @var Deploy\Kubectl */
    private $kubectl;

    /** @var callable[] */
    private $before = [];

    /** @var callable[] */
    private $after = [];

    public function __construct(Deploy\Kubectl $kubectl)
    {
        parent::__construct();
        $this->kubectl = $kubectl;
    }

    protected function configure(): void
    {
        $this->setName('deploy')
            ->setDescription('Deploys to Kubernetes')
            ->addArgument(
                self::ARG_REVISION,
                InputArgument::OPTIONAL,
                'The revision or full git commit hash to deploy',
                'master'
            )
            ->addOption(
                self::OPT_DRY_RUN,
                null,
                null,
                'Print the command but do not run it'
            )
            ;
    }

    public function before(callable $before): void
    {
        $this->before[] = $before;
    }

    public function after(callable $after): void
    {
        $this->after[] = $after;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);
        $this->kubectl->setLogger($logger);

        $rev = $input->getArgument(self::ARG_REVISION);
        assert(is_string($rev));
        $hash = $this->getRevisionToDeploy($rev);

        $isDryRun = (bool)$input->getOption(self::OPT_DRY_RUN);

        $this->kubectl->setDryRun($isDryRun);
        $this->beforeDeploy($hash, $rev, $isDryRun);
        $this->kubectl->deploy($hash);
        $this->afterDeploy($hash, $rev, $isDryRun);
        $logger->info('Deployed {rev}', ['rev' => $hash]);

        return 0;
    }

    private function getRevisionToDeploy(string $rev): string
    {
        $process = new Process(['git', 'rev-parse', $rev]);
        $process->mustRun();
        return trim($process->getOutput());
    }

    protected function afterDeploy(string $hash, string $rev, bool $isDryRun): void
    {
        foreach ($this->after as $after) {
            $after($hash, $rev, $isDryRun);
        }
    }

    protected function beforeDeploy(string $hash, string $rev, bool $isDryRun): void
    {
        foreach ($this->before as $before) {
            $before($hash, $rev, $isDryRun);
        }
    }
}
