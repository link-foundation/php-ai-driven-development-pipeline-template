<?php

declare(strict_types=1);

// Read-only comparison: never send a Git push POST or print credentials.
require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

use LinkFoundation\Template\Pipeline\Http;
use LinkFoundation\Template\Pipeline\Process;
use LinkFoundation\Template\Pipeline\PreflightCheck;
use LinkFoundation\Template\Pipeline\ReleasePreflight;

$repository = 'link-foundation/php-ai-driven-development-pipeline-template';
$token = getenv('GITHUB_TOKEN') ?: trim(Process::run(['gh', 'auth', 'token'])->stdout);
$http = new Http();
$url = "https://github.com/{$repository}.git/info/refs?service=git-receive-pack";

foreach (['anonymous' => [], 'authenticated' => [
    'Authorization' => 'Basic ' . base64_encode("x-access-token:{$token}"),
]] as $name => $headers) {
    $response = $http->get($url, $headers);
    echo json_encode([
        'probe' => $name,
        'status' => $response['status'],
        'receivePackAdvertisement' => str_starts_with($response['body'], "001f# service=git-receive-pack\n0000"),
    ], JSON_THROW_ON_ERROR) . "\n";
}

$check = (new ReleasePreflight(
    packageName: 'vendor/pkg',
    githubRepository: $repository,
    githubToken: $token,
    trace: static function (string $message): void {
        fwrite(STDERR, "[preflight] {$message}\n");
    },
))->checkGitHubPushPermission();
echo json_encode(['status' => $check->status, 'detail' => $check->detail], JSON_THROW_ON_ERROR) . "\n";
exit($check->status === PreflightCheck::VERIFIED ? 0 : 1);
