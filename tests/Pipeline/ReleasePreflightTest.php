<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Http;
use LinkFoundation\Template\Pipeline\PreflightCheck;
use LinkFoundation\Template\Pipeline\ReleasePreflight;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleasePreflightTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     * @return array{0: string, 1: string, 2: array<string, string>}
     */
    private static function capture(string $method, string $url, array $headers): array
    {
        return [$method, $url, $headers];
    }

    private function preflightAnswering(int $status, string $body): ReleasePreflight
    {
        return new ReleasePreflight(
            packageName: 'vendor/pkg',
            githubRepository: 'owner/repo',
            http: new Http(static fn (): array => ['status' => $status, 'body' => $body]),
            githubToken: 'token',
        );
    }

    public function testRegisteredPackageVerifies(): void
    {
        $check = $this->preflightAnswering(200, '{}')->checkPackagistRegistration();

        self::assertSame(PreflightCheck::VERIFIED, $check->status);
        self::assertStringContainsString('vendor/pkg', $check->detail);
    }

    public function testUnregisteredPackageFailsWithTheFix(): void
    {
        $check = $this->preflightAnswering(404, '')->checkPackagistRegistration();

        self::assertSame(PreflightCheck::FAILED, $check->status);
        self::assertStringContainsString('packagist.org', $check->detail);
    }

    public function testRateLimitedRegistryStaysUnknown(): void
    {
        $check = $this->preflightAnswering(429, '')->checkPackagistRegistration();

        self::assertSame(PreflightCheck::UNKNOWN, $check->status);
        self::assertStringContainsString('answered 429', $check->detail);
    }

    public function testSilentRegistryStaysUnknown(): void
    {
        $check = $this->preflightAnswering(0, '')->checkPackagistRegistration();

        self::assertSame(PreflightCheck::UNKNOWN, $check->status);
        self::assertStringContainsString('did not answer', $check->detail);
    }

    public function testPackagistProbeAsksTheMetadataEndpointTheWaitLoopPolls(): void
    {
        $seen = null;
        $http = new Http(static function (string $method, string $url) use (&$seen): array {
            $seen = self::capture($method, $url, []);

            return ['status' => 200, 'body' => '{}'];
        });

        $preflight = new ReleasePreflight(
            packageName: 'Vendor/Pkg',
            githubRepository: 'owner/repo',
            http: $http,
            githubToken: 'token',
        );
        $preflight->checkPackagistRegistration();

        self::assertIsArray($seen);
        self::assertSame('GET', $seen[0]);
        self::assertSame('https://repo.packagist.org/p2/vendor/pkg.json', $seen[1]);
    }

    public function testPushPermissionVerifies(): void
    {
        $body = "001f# service=git-receive-pack\n00000000";

        $check = $this->preflightAnswering(200, $body)->checkGitHubPushPermission();

        self::assertSame(PreflightCheck::VERIFIED, $check->status);
    }

    public function testActionsTokenUsesGitAuthorizationInsteadOfCollaboratorPermissions(): void
    {
        $http = new Http(static function (string $method, string $url): array {
            return [
                'status' => 200,
                'body' => str_contains($url, '/info/refs?service=git-receive-pack')
                    ? "001f# service=git-receive-pack\n00000000"
                    : '{"permissions":{"push":false}}',
            ];
        });
        $preflight = new ReleasePreflight(
            packageName: 'vendor/pkg',
            githubRepository: 'owner/repo',
            http: $http,
            githubToken: 'actions-installation-token',
        );

        self::assertSame(PreflightCheck::VERIFIED, $preflight->checkGitHubPushPermission()->status);
    }

    public function testReadOnlyTokenFailsWithWhereToFixIt(): void
    {
        $check = $this->preflightAnswering(403, 'Write access not granted.')->checkGitHubPushPermission();

        self::assertSame(PreflightCheck::FAILED, $check->status);
        self::assertStringContainsString('Workflow permissions', $check->detail);
    }

    public function testRefusedRepositoryFails(): void
    {
        $check = $this->preflightAnswering(403, '')->checkGitHubPushPermission();

        self::assertSame(PreflightCheck::FAILED, $check->status);
        self::assertStringContainsString('answered 403', $check->detail);
    }

    /** @return iterable<string, array{int, string}> */
    public static function unverifiedGitResponses(): iterable
    {
        yield 'no connection' => [0, ''];
        yield 'rate limit' => [429, ''];
        yield 'outage' => [503, ''];
        yield 'login page' => [200, '<html>Sign in</html>'];
        yield 'collaborator permissions' => [200, '{"permissions":{"push":true}}'];
        yield 'read service' => [200, "001e# service=git-upload-pack\n00000000"];
        yield 'truncated advertisement' => [200, "001f# service=git-receive-pack\n0000000"];
    }

    #[DataProvider('unverifiedGitResponses')]
    public function testUnansweredOrUnexpectedGitResponseStaysUnknown(int $status, string $body): void
    {
        self::assertSame(
            PreflightCheck::UNKNOWN,
            $this->preflightAnswering($status, $body)->checkGitHubPushPermission()->status,
        );
    }

    public function testOptionalDiagnosticsExcludeCredentialsAndResponseBodies(): void
    {
        $messages = [];
        $preflight = new ReleasePreflight(
            packageName: 'vendor/pkg',
            githubRepository: 'owner/repo',
            http: new Http(static fn (): array => ['status' => 403, 'body' => 'private response']),
            githubToken: 'secret-token',
            trace: static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
        );
        $preflight->checkGitHubPushPermission();

        self::assertSame(['Git receive-pack discovery: HTTP 403; push advertisement: no.'], $messages);
    }

    public function testMissingTokenStaysUnknown(): void
    {
        $preflight = new ReleasePreflight(
            packageName: 'vendor/pkg',
            githubRepository: 'owner/repo',
            http: new Http(static fn (): array => ['status' => 200, 'body' => '{}']),
            githubToken: '',
        );

        $check = $preflight->checkGitHubPushPermission();

        self::assertSame(PreflightCheck::UNKNOWN, $check->status);
    }

    public function testGitHubProbeAuthenticatesAsTheTokenItJudges(): void
    {
        $seen = null;
        $http = new Http(static function (string $method, string $url, array $headers) use (&$seen): array {
            $seen = self::capture($method, $url, $headers);

            return ['status' => 200, 'body' => "001f# service=git-receive-pack\n00000000"];
        });

        $preflight = new ReleasePreflight(
            packageName: 'vendor/pkg',
            githubRepository: 'owner/repo',
            http: $http,
            githubToken: 'secret-token',
        );
        $preflight->checkGitHubPushPermission();

        self::assertIsArray($seen);
        self::assertSame('GET', $seen[0]);
        self::assertSame('https://github.com/owner/repo.git/info/refs?service=git-receive-pack', $seen[1]);
        self::assertSame('Basic ' . base64_encode('x-access-token:secret-token'), $seen[2]['Authorization']);
        self::assertSame('application/x-git-receive-pack-advertisement', $seen[2]['Accept']);
    }
}
