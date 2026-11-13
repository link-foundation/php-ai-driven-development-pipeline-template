import { describe, it, expect } from 'test-anywhere';
import { createRequire } from 'node:module';
const { fixArchitectureSelection } = createRequire(import.meta.url)(
  '../examples/universal-app/scripts/fix-winstaller.cjs'
);

describe('published Windows installer architecture selection', () => {
  it('calls os.arch and preserves already-fixed upstream scripts', () => {
    const broken = 'const arch = os.arch;';
    const fixed = fixArchitectureSelection(broken);
    const selected = new Function('os', `${fixed} return arch;`)({
      arch: () => 'x64',
    });
    expect(selected).toBe('x64');
    expect(fixArchitectureSelection(fixed)).toBe(fixed);
  });
});
