from pathlib import Path
import re
root=Path('docs/case-studies/issue-1/data/templates/js/scripts')
for p in root.glob('*.mjs'):
 s=p.read_text()
 if 'function setOutput(' in s:
  s=re.sub(r'(?:/\*\*\n(?: \*[^\n]*\n)* \*/\n)?(?:async )?function setOutput\([^\n]*\) \{.*?^\}\n', '', s, flags=re.M|re.S)
  s="import { setOutput } from '@actions/core';\n"+s if not s.startswith('#!') else s.replace('\n','\nimport { setOutput } from \'@actions/core\';\n',1)
 # Native Node import.meta.main replaces duplicated CLI guard implementations.
 if 'function isCliEntryPoint()'in s:
  s=re.sub(r'function isCliEntryPoint\(\) \{.*?^\}\n','',s,flags=re.M|re.S).replace('if (isCliEntryPoint())','if (import.meta.main)')
 s=s.replace(' * - use-m: Dynamic package loading without package.json dependencies\n','')
 p.write_text(s)
# Error diagnostics remain opt-in across all release scripts.
(root/'script-errors.mjs').write_text('''export function failScript(context, error) {
  console.error(`${context}:`, error.message);
  if (process.env.DEBUG) console.error('Stack trace:', error.stack);
  process.exit(1);
}
''')
for p in root.glob('*.mjs'):
 if p.name=='script-errors.mjs': continue
 s=p.read_text(); used=False
 pattern=r"console.error\('([^']+):', error.message\);\s*(?:if \(process.env.DEBUG\) \{\s*console.error\('Stack trace:', error.stack\);\s*\}\s*)?process.exit\(1\);"
 def repl(m):
  return f"failScript('{m[1]}', error);"
 s,n=re.subn(pattern,repl,s)
 if n:s=s.replace('\n',"\nimport { failScript } from './script-errors.mjs';\n",1)
 p.write_text(s)
# Shared command execution for scripts that intentionally report-and-continue.
(root/'exec.mjs').write_text('''import { execSync } from 'node:child_process';

export function exec(command) {
  try {
    return execSync(command, { encoding: 'utf-8' }).trim();
  } catch (error) {
    console.error(`Error executing command: ${command}`);
    console.error(error.message);
    return '';
  }
}
''')
for name in ['check-version','detect-code-changes']:
 p=root/f'{name}.mjs';s=p.read_text();s=re.sub(r'function exec\(command\) \{.*?^\}\n','',s,flags=re.M|re.S);s=s.replace('\n',"\nimport { exec } from './exec.mjs';\n",1);p.write_text(s)
