import { readWorkflow, getJobBlock } from './workflow-helpers.js';
import { describe, it, expect } from 'test-anywhere';
import { readFileSync } from 'node:fs';
import { parse } from 'yaml';

describe('workflow reliability policy', () => {
  it('cancels superseded non-main runs without cancelling main runs', () => {
    const workflowPaths = [
      '.github/workflows/example-app.yml',
      '.github/workflows/release.yml',
    ];

    for (const workflowPath of workflowPaths) {
      const workflow = readWorkflow(workflowPath);

      expect(workflow).toContain(
        workflowPath.endsWith('example-app.yml')
          ? 'group: example-app-${{ github.ref }}'
          : 'group: ${{ github.workflow }}-${{ github.ref }}'
      );
      expect(workflow).toContain(
        "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}"
      );
      expect(workflow).not.toContain(
        "cancel-in-progress: ${{ github.ref == 'refs/heads/main' }}"
      );
    }
  });

  it('uploads preview regeneration artifacts when screenshot rendering fails', () => {
    const exampleAppWorkflow = readWorkflow(
      '.github/workflows/example-app.yml'
    );
    const previewRegenJob = getJobBlock(exampleAppWorkflow, 'preview-regen');

    expect(previewRegenJob).toContain(
      'name: Upload screenshot failure artifacts'
    );
    expect(previewRegenJob).toContain('if: failure()');
    expect(previewRegenJob).toContain('uses: actions/upload-artifact@v7');
    expect(previewRegenJob).toContain(
      'name: preview-regen-failure-${{ github.run_id }}'
    );
    expect(previewRegenJob).toContain('docs/screenshots/');
    expect(previewRegenJob).toContain('web/test-results/');
    expect(previewRegenJob).toContain('web/playwright-report/');
    expect(previewRegenJob).toContain('retention-days: 7');
    expect(previewRegenJob).toContain('if-no-files-found: ignore');
  });

  it('uses the official Playwright image for preview regeneration instead of downloading Chromium', () => {
    const exampleAppWorkflow = readWorkflow(
      '.github/workflows/example-app.yml'
    );
    const previewRegenJob = getJobBlock(exampleAppWorkflow, 'preview-regen');
    const imageVersion = previewRegenJob.match(
      /image:\s*mcr\.microsoft\.com\/playwright:v([0-9.]+)-noble/
    )?.[1];
    const packageVersion = JSON.parse(
      readFileSync('package.json', 'utf8')
    ).devDependencies.playwright.slice(1);

    expect(previewRegenJob).toContain('container:');
    const lockedVersion = JSON.parse(readFileSync('package-lock.json', 'utf8'))
      .packages['node_modules/playwright'].version;
    expect(imageVersion).toBe(lockedVersion);
    expect(packageVersion).toBe(imageVersion);
    expect(previewRegenJob).toContain("PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD: '1'");
    expect(previewRegenJob).not.toContain('npx playwright install');
    expect(previewRegenJob).not.toContain('~/.cache/ms-playwright');
  });

  it('verifies desktop package output before uploading artifacts', () => {
    const exampleAppWorkflow = readWorkflow(
      '.github/workflows/example-app.yml'
    );
    const desktopPackageJob = getJobBlock(
      exampleAppWorkflow,
      'desktop-package'
    );
    const packageStepIndex = desktopPackageJob.indexOf(
      'name: Package Electron app'
    );
    const uploadStepIndex = desktopPackageJob.indexOf(
      'name: Upload desktop package'
    );

    expect(packageStepIndex).toBeGreaterThanOrEqual(0);
    expect(uploadStepIndex).toBeGreaterThan(packageStepIndex);
    const setupNode = parse(exampleAppWorkflow).jobs[
      'desktop-package'
    ].steps.find((step) => step.uses?.startsWith('actions/setup-node@'));
    expect(setupNode.with['node-version']).toBe('26.13.1');
    expect(desktopPackageJob).not.toContain("node-version: '24.x'");
    expect(desktopPackageJob).toContain('shell: bash');
    expect(desktopPackageJob).toContain('npm run example:desktop:package');
    expect(desktopPackageJob).toContain('find examples/universal-app/out');
    expect(desktopPackageJob).toContain(
      'Desktop package output was not created at examples/universal-app/out'
    );
    expect(desktopPackageJob).toContain('if-no-files-found: error');
  });
});
