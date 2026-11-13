from pathlib import Path
import re
r=Path('docs/case-studies/issue-1/data/templates/js')
for p in (r/'.github/workflows').glob('*.yml'):
 s=p.read_text();counter=0;seen={}
 pattern=r'^      - [^\n]*\n(?:(?: {8}[^\n]*|)\n)*'
 def repl(m):
  global counter
  block=m[0].rstrip();lines=block.splitlines()
  if len(lines)<3:return m[0]
  if block in seen:return f'      - *{seen[block]}\n\n'
  # Anchor only blocks which actually recur.
  if s.count(block)<2:return m[0]
  counter+=1;name=f'shared-step-{counter}';seen[block]=name
  return f'      - &{name}\n        '+lines[0][8:]+'\n'+'\n'.join(lines[1:])+'\n\n'
 s=re.sub(pattern,repl,s,flags=re.M)
 if p.name=='release.yml':
  permissions='''    permissions:
      contents: write
      pull-requests: write
      id-token: write'''
  s=s.replace(permissions,permissions.replace('permissions:','permissions: &publish-permissions'),1)
  s=s.replace(permissions,'    permissions: *publish-permissions')
  outputs='''    outputs:
      published: ${{ steps.publish.outputs.published }}
      published_version: ${{ steps.publish.outputs.published_version }}'''
  s=s.replace(outputs,outputs.replace('outputs:','outputs: &publish-outputs'),1)
  s=s.replace(outputs,'    outputs: *publish-outputs')
  # Use the detector's aggregate result, including package manifest changes.
  s=s.replace("      needs.detect-changes.outputs.mjs-changed == 'true' ||\n      needs.detect-changes.outputs.js-changed == 'true'", "      needs.detect-changes.outputs.any-code-changed == 'true'")
 if p.name=='example-app.yml':s=s.replace('group: ${{ github.workflow }}-${{ github.ref }}','group: example-app-${{ github.ref }}')
 p.write_text(s)
p=r/'tests/workflow-reliability.test.js';s=p.read_text().replace("'group: ${{ github.workflow }}-${{ github.ref }}'", "workflowPath.endsWith('example-app.yml') ? 'group: example-app-${{ github.ref }}' : 'group: ${{ github.workflow }}-${{ github.ref }}'");p.write_text(s)
