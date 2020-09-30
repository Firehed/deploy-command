<?php
declare(strict_types=1);

namespace Firehed\Console\Deploy;

use RuntimeException;
use Psr\Log;
use Symfony\Component\Process\Process;

class Kubectl implements Log\LoggerAwareInterface
{
    use Log\LoggerAwareTrait;

    /**
     * @var array{
     *   container: string,
     *   deployment: string,
     *   image: string,
     *   namespace: string,
     * }[]
     */
    private $deployments;

    /** @var bool */
    private $dryRun = false;

    /**
     * A list of deployment configuration structures. Each must have the
     * shape described in the type annotation.
     *
     * Namespace is optional, and will default to 'default'
     *
     * @param array{
     *   container: string,
     *   deployment: string,
     *   image: string,
     *   namespace: string,
     * }[] $deployments Deployment configs
     */
    public function __construct(array $deployments)
    {
        if (!`which kubectl`) {
            throw new RuntimeException('kubectl not detected');
        }

        foreach ($deployments as $i => $dep) {
            if (!isset($dep['container'], $dep['image'], $dep['deployment'])) {
                throw new RuntimeException('Invalid deployment');
            }
        }
        $this->deployments = $deployments;
        $this->logger = new Log\NullLogger();
    }

    public function deploy(string $tag): void
    {
        foreach ($this->deployments as $deployment) {
            $this->execute($deployment, $tag);
        }
    }

    public function setDryRun(bool $isDryRun): void
    {
        $this->dryRun = $isDryRun;
    }

    /**
     * @param array{
     *   container: string,
     *   deployment: string,
     *   image: string,
     *   namespace: ?string,
     * } $params Deployment configs
     */
    private function execute(array $params, string $tag): void
    {
        $deployment = $params['deployment'];
        $container = $params['container'];
        $image = strtr($params['image'], ['$IMAGE' => $tag]);
        $namespace = $params['namespace'] ?? 'default';

        $command = sprintf(
            'kubectl set image deploy --namespace %s %s %s=%s',
            $namespace,
            $deployment,
            $container,
            $image
        );

        $this->logger->debug($command);

        if ($this->dryRun) {
            $this->logger->warning('Skipping due to dry-run mode');
            return;
        }

        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        $this->logger->debug($process->getOutput());
    }
}
