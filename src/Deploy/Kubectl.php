<?php
declare(strict_types=1);

namespace Firehed\Console\Deploy;

use RuntimeException;
use Psr\Log;
use Symfony\Component\Process\Process;

class Kubectl implements Log\LoggerAwareInterface
{
    use Log\LoggerAwareTrait;

    private $deployments;

    private $dryRun = false;

    /**
     * A list of deployment configuration structures. Each must have the
     * following shape:
     *
     * [
     *   'container' => string,
     *   'deployment' => 'string',
     *   'image' => string,
     *   'namespace' => string,
     * ]
     *
     * Namespace is optional, and will default to 'default'
     *
     * @param array $deployments Deployment configs
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

    public function deploy(string $tag)
    {
        foreach ($this->deployments as $deployment) {
            $this->execute($deployment, $tag);
        }
    }

    public function setDryRun(bool $isDryRun)
    {
        $this->dryRun = $isDryRun;
    }

    private function execute(array $params, string $tag)
    {
        $deployment = $params['deployment'];
        $container = $params['container'];
        $image = strtr($params['image'], ['$IMAGE' => $tag]);
        $namespace = $params['namespace'] ?? 'default';

        $command = sprintf(
            'kubectl set image deploy --namespace %s %s %s=%s --record',
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

        $process = new Process($command);
        $process->mustRun();
        $this->logger->debug($process->getOutput());
    }
}
