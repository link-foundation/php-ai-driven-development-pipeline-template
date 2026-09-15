<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\WorkflowConcurrency;
use PHPUnit\Framework\TestCase;

final class WorkflowConcurrencyTest extends TestCase
{
    public function testReadsEveryEffectiveConcurrencyState(): void
    {
        $yaml = <<<'YAML'
            name: Test
            concurrency:
              group: workflow
              cancel-in-progress: true
            jobs:
              inherited:
                runs-on: ubuntu-latest
              literal-false:
                concurrency:
                  group: false-group
                  cancel-in-progress: false # do not interrupt publishing
              scalar:
                concurrency: scalar-group
              expression:
                concurrency:
                  group: expression-group
                  cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}
              invalid:
                concurrency:
                  group: invalid-group
                  cancel-in-progress: perhaps
            YAML;

        self::assertSame([
            'inherited' => WorkflowConcurrency::TRUE,
            'literal-false' => WorkflowConcurrency::FALSE,
            'scalar' => WorkflowConcurrency::FALSE,
            'expression' => WorkflowConcurrency::UNKNOWN,
            'invalid' => WorkflowConcurrency::UNKNOWN,
            'absent' => WorkflowConcurrency::MISSING,
        ], WorkflowConcurrency::read($yaml, [
            'inherited',
            'literal-false',
            'scalar',
            'expression',
            'invalid',
            'absent',
        ]));
    }

    public function testNoConcurrencyGroupIsNotCancellable(): void
    {
        $yaml = "name: Test\njobs:\n  build:\n    runs-on: ubuntu-latest\n";

        self::assertSame(
            ['build' => WorkflowConcurrency::NONE],
            WorkflowConcurrency::read($yaml, ['build']),
        );
    }

    public function testJobLevelValueOverridesWorkflowValue(): void
    {
        $yaml = <<<'YAML'
            concurrency:
              group: workflow
              cancel-in-progress: false
            jobs:
              build:
                concurrency:
                  group: build
                  cancel-in-progress: 'true'
            YAML;

        self::assertSame(
            ['build' => WorkflowConcurrency::TRUE],
            WorkflowConcurrency::read($yaml, ['build']),
        );
    }
}
