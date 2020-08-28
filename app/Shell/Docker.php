<?php

namespace App\Shell;

use Exception;
use Symfony\Component\Process\Process;

class Docker
{
    protected $shell;

    public function __construct(Shell $shell)
    {
        $this->shell = $shell;
    }

    public function removeContainer(string $containerId): void
    {
        $this->stopContainer($containerId);

        $process = $this->shell->exec('docker rm ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed removing container ' . $containerId);
        }
    }

    public function stopContainer(string $containerId): void
    {
        $process = $this->shell->exec('docker stop ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed stopping container ' . $containerId);
        }
    }

    public function isInstalled(): bool
    {
        $process = $this->shell->execQuietly('docker --version 2>&1');

        return $process->isSuccessful();
    }

    public function takeoutContainers(): array
    {
        return $this->containerRawOutputToArray($this->takeoutContainersRawOutput());
    }

    public function allContainers(): array
    {
        return $this->containerRawOutputToArray($this->allContainersRawOutput());
    }

    protected function containerRawOutputToArray($output): array
    {
        return array_filter(array_map(function ($line) {
            return explode('|', $line);
        }, explode("\n", $output)));
    }

    protected function takeoutContainersRawOutput(): string
    {
        $dockerProcessStatusString = 'docker ps -a --filter "name=TO-" --format "table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}"';
        return trim($this->shell->execQuietly($dockerProcessStatusString)->getOutput());
    }

    protected function allContainersRawOutput(): string
    {
        $dockerProcessStatusString = 'docker ps -a --format "table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}"';
        return trim($this->shell->execQuietly($dockerProcessStatusString)->getOutput());
    }

    public function imageIsDownloaded(string $organization, string $imageName, ?string $tag): bool
    {
        $process = $this->shell->execQuietly(sprintf(
            'docker image inspect %s/%s:%s',
            $organization,
            $imageName,
            $tag
        ));

        return $process->isSuccessful();
    }

    public function downloadImage(string $organization, string $imageName, ?string $tag): void
    {
        $this->shell->exec(sprintf(
            'docker pull %s/%s:%s',
            $organization,
            $imageName,
            $tag
        ));
    }

    public function bootContainer(string $dockerRunTemplate, array $parameters): void
    {
        $process = $this->shell->exec('docker run -d --name "$container_name" ' . $dockerRunTemplate, $parameters);

        if (! $process->isSuccessful()) {
            throw new Exception("Failed installing {$containerName}");
        }
    }

    public function attachedVolumeName(string $containerId)
    {
        $response = $this->shell->execQuietly("docker inspect --format='{{json .Mounts}}' {$containerId}");
        $jsonResponse = json_decode($response->getOutput());
        return optional($jsonResponse)[0]->Name ?? null;
    }

    public function isDockerServiceRunning(): bool
    {
        $response = $this->shell->execQuietly('pgrep -f /usr/bin/dockerd');
        return $response->isSuccessful();
    }

    public function stopDockerService(): void
    {
        $this->shell->execQuietly("test -z $(docker ps -q 2>/dev/null) && osascript -e 'quit app \"Docker\"'");
    }
}
