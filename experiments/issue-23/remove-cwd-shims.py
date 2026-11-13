from pathlib import Path
import re
root=Path('docs/case-studies/issue-1/data/templates/js/scripts')
for name in ['changeset-version','instant-version-bump','version-and-commit','publish-to-npm']:
 p=root/f'{name}.mjs'; s=p.read_text()
 s=s.replace('getJsRoot, needsCd, parseJsRootConfig','getJsRoot, parseJsRootConfig').replace('  needsCd,\n','')
 s=re.sub(r'// Store the original working directory to restore after cd commands\n// IMPORTANT: command-stream[^\n]*\nconst originalCwd = process.cwd\(\);\n\n','',s)
 s=re.sub(r'^\s*// IMPORTANT: cd is a virtual command[^\n]*\n','\n',s,flags=re.M)
 pattern=r'(?P<indent> +)if \(needsCd\(\{ jsRoot \}\)\) \{\n +await \$`cd \$\{jsRoot\} && (?P<cmd>[^`]+)`;\n +process.chdir\(originalCwd\);\n +\} else \{\n +await \$`(?P=cmd)`;\n +\}'
 s,n=re.subn(pattern,lambda m: f'{m["indent"]}await $({{ cwd: jsRoot }})`{m["cmd"]}`;',s)
 if name=='publish-to-npm':
  start=s.index('async function runChangesetPublish('); end=s.index('\n/**',start)
  s=s[:start]+'''async function runChangesetPublish(shell, jsRoot) {
  try {
    const result = await shell({ cwd: jsRoot })`npm run changeset:publish`.run({
      capture: true,
    });
    return { result, error: null };
  } catch (error) {
    return { result: null, error };
  }
}
'''+s[end:]
  s=s.replace(' * @param {string} originalCwd\n','')
  s=re.sub(r',\n(\s*)originalCwd\n',r'\n',s)
 s=re.sub(r'^\s*// Restore cwd on error[^\n]*\n','\n',s,flags=re.M)
 s=re.sub(r'^\s*process.chdir\(originalCwd\);\n','\n',s,flags=re.M)
 s=s.replace(' * - use-m: Dynamic package loading without package.json dependencies\n','')
 s=s.replace(' * - Reference: link-assistant/agent PR #112 (--legacy-peer-deps fix)\n','')
 s=s.replace('  // This addresses npm ERESOLVE errors documented in issue #111 / PR #112\n','')
 p.write_text(s); print(name,n)
