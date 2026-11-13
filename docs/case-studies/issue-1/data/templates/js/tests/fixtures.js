import { rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

export function createTempDir(prefix) {
  return mkdtempSync(join(tmpdir(), prefix));
}

export function withFixture(create, verify) {
  const root = create();
  try {
    return verify(root);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}
