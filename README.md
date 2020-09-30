# Symfony Console Deploy Command

[![Latest Stable Version](https://poser.pugx.org/firehed/deploy-command/v)](//packagist.org/packages/firehed/deploy-command)
[![License](https://poser.pugx.org/firehed/deploy-command/license)](//packagist.org/packages/firehed/deploy-command)

[![Test](https://github.com/Firehed/deploy-command/workflows/Test/badge.svg)](https://github.com/Firehed/deploy-command/actions?query=workflow%3ATest)
[![Lint](https://github.com/Firehed/deploy-command/workflows/Lint/badge.svg)](https://github.com/Firehed/deploy-command/actions?query=workflow%3ALint)
[![Static analysis](https://github.com/Firehed/deploy-command/workflows/Static%20analysis/badge.svg)](https://github.com/Firehed/deploy-command/actions?query=workflow%3A%22Static+analysis%22)
[![codecov](https://codecov.io/gh/Firehed/deploy-command/branch/master/graph/badge.svg)](https://codecov.io/gh/Firehed/deploy-command)

This is a premade Symfony Console component to manually deploy your application.
At this time, it only supports deployment to Kubernetes via `kubectl`.
Deploying multiple images at once _is_ supported.

Ultimately, it's a fancy wrapper around running `kubectl set image deploy ...`.

## Configuration and Usage

`composer require firehed/deploy-command`

Somewhere in your existing Symfony Console setup or config:

```php
$targets = [
    [
        'container' => 'your-container-name',
        'deployment' => 'your-deployment-name',
        'image' => 'yourco/yourimage:$IMAGE',
        'namespace' => 'your-deployment-namespace',
    ], [
        // Another thing to deploy at the same time
    ]
];
$kubectl = new Firehed\Console\Deploy\Kubectl($targets);
$deploy = new Firehed\Console\Deploy($kubectl);

$application = new Symfony\Component\Console\Application();
// ...
$application->add($deploy);
$application->run();
```

In your `image`, `$IMAGE` will be substituted with the commit hash of the command argument, or that of `master`.
It is NOT a PHP variable in the above example (note single quotes).
`namespace` is optional, and will default to Kubernetes' `default`.

### Event hooks

The deploy command will run any event hooks you request before or after the deployment process runs.
To register a hook, call `$deploy->before($hook);` or `$deploy->after($hook);`
The hook must be a callable with the signature `function (string $hash, string $revision, bool $isDryRun)`.
Hooks were designed primarily for sending notifications (e.g. posting to Slack) but can be used for whatever you want.

## Requirements and Limitations

This only works in git repositories, and expects that your docker image will be tagged with the full 40-character commit hash (e.g. `yourname/yourfancyproject:92dac20583b35ea7167366bbf0b24243016911c0`).
This is only a deployment tool, and does not perform the builds.

All of the images deployed will use the same hash, and all deploy together.
Selective deployment is not supported at this time.
