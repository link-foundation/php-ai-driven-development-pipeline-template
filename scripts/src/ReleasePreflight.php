<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/**
 * Proves the release preconditions before the pipeline spends an hour building
 * on top of them (issue #11).
 *
 * The PHP template publishes through two preconditions that today are only
 * discovered broken at the very end of the run:
 *
 * - Packagist must know the package. The release reaches Packagist through its
 *   webhook, and `wait-for-packagist.php` is the first step that ever asks --
 *   deep into the run and after the version tag has already been pushed. The
 *   probe is a single unauthenticated GET of the p2 metadata the wait loop
 *   polls anyway.
 * - The workflow token must be able to push. `create-github-release.php` runs
 *   last; a repository whose Actions workflow permissions are read-only only
 *   fails there. Probe Git's receive-pack discovery endpoint, which checks
 *   push authorization without sending objects or changing any refs. The REST
 *   repository permissions block describes collaborator access and can report
 *   false for an Actions installation token that has contents: write.
 *
 * Probing with a real answer (the metadata GET, Git authorization) is the
 * point: a login-style check would report a credential as good while the write
 * it is meant to prove is silently missing.
 */
final class ReleasePreflight
{
    /** @param ?\Closure(string): void $trace Optional diagnostics; never includes credentials or response bodies. */
    public function __construct(
        private readonly string $packageName,
        private readonly string $githubRepository,
        private readonly Packagist $packagist = new Packagist(),
        private readonly Http $http = new Http(),
        private readonly string $githubToken = '',
        private readonly ?\Closure $trace = null,
    ) {
    }

    public function checkPackagistRegistration(): PreflightCheck
    {
        $name = 'Packagist knows the package';
        $response = $this->http->get($this->packagist->metadataUrl($this->packageName));

        return match ($response['status']) {
            200 => new PreflightCheck(
                $name,
                PreflightCheck::VERIFIED,
                "Packagist has metadata for {$this->packageName}.",
            ),
            404 => new PreflightCheck(
                $name,
                PreflightCheck::FAILED,
                "Packagist has no metadata for {$this->packageName}: the package is not "
                . 'registered, so a pushed tag will never reach Packagist. Submit the '
                . 'package on packagist.org before releasing.',
            ),
            default => $this->unanswered($name, $response['status'], 'repo.packagist.org'),
        };
    }

    public function checkGitHubPushPermission(): PreflightCheck
    {
        $name = 'The workflow token can push to the repository';

        if ($this->githubToken === '') {
            return new PreflightCheck(
                $name,
                PreflightCheck::UNKNOWN,
                'No GITHUB_TOKEN is available; the push permission could not be probed.',
            );
        }

        $response = $this->http->get(
            "https://github.com/{$this->githubRepository}.git/info/refs?service=git-receive-pack",
            [
                'Authorization' => 'Basic ' . base64_encode("x-access-token:{$this->githubToken}"),
                'Accept' => 'application/x-git-receive-pack-advertisement',
            ],
        );

        $advertisementHeader = "001f# service=git-receive-pack\n0000";
        $advertisesPush = str_starts_with($response['body'], $advertisementHeader)
            && \strlen($response['body']) >= \strlen($advertisementHeader) + 4
            && str_ends_with($response['body'], '0000');
        $this->trace?->__invoke(\sprintf(
            'Git receive-pack discovery: HTTP %d; push advertisement: %s.',
            $response['status'],
            $advertisesPush ? 'yes' : 'no',
        ));

        if (\in_array($response['status'], [401, 403, 404], true)) {
            return new PreflightCheck(
                $name,
                PreflightCheck::FAILED,
                "github.com answered {$response['status']} for Git push discovery on {$this->githubRepository}; "
                . 'check token access and Settings -> Actions -> General -> Workflow permissions, '
                . 'and grant the release job contents: write.',
            );
        }

        if ($response['status'] !== 200) {
            return $this->unanswered($name, $response['status'], 'github.com');
        }

        if ($advertisesPush) {
            return new PreflightCheck(
                $name,
                PreflightCheck::VERIFIED,
                "Git authorizes this token to push to {$this->githubRepository}; no refs were changed.",
            );
        }

        return new PreflightCheck(
            $name,
            PreflightCheck::UNKNOWN,
            'github.com answered 200 without a complete Git receive-pack advertisement; '
            . 'push authorization is undetermined.',
        );
    }

    private function unanswered(string $name, int $status, string $host): PreflightCheck
    {
        $reason = $status === 0
            ? "{$host} did not answer"
            : "{$host} answered {$status}";

        return new PreflightCheck(
            $name,
            PreflightCheck::UNKNOWN,
            "{$reason} (rate limit or outage); the precondition is undetermined, not met.",
        );
    }
}
