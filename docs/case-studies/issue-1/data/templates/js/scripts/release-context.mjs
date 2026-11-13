import { getChangesetDir, getJsRoot, parseJsRootConfig } from './js-paths.mjs';
import { readPackageInfo } from './package-info.mjs';

export function releaseContext() {
  const jsRoot = getJsRoot({ jsRoot: parseJsRootConfig(), verbose: true });
  const changesetDir = getChangesetDir({ jsRoot });
  const { name: packageName } = readPackageInfo({ jsRoot });
  return { jsRoot, changesetDir, packageName };
}
