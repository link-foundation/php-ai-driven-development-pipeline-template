"""Preserve local commit records while removing accidental build binaries before publication."""
import os, subprocess
from pathlib import Path

def git(*args, **kw):
    return subprocess.check_output(['git',*args],text=True,**kw).strip()
old=git('rev-parse','HEAD')
assert git('branch','--show-current')=='issue-23-addf7c5fd0d4'
assert git('ls-remote','origin','refs/heads/issue-23-addf7c5fd0d4').startswith('b227841')
git('update-ref','refs/issue-23/unpublished-build-output',old)
parent=git('rev-parse','164c8a3')
records=[]
for commit in ['94c8c03','d95d797']:
    index=Path('/tmp/issue-23-clean-index')
    index.unlink(missing_ok=True)
    env={**os.environ,'GIT_INDEX_FILE':str(index)}
    git('read-tree',commit,env=env)
    git('rm','--cached','--quiet','-r','docs/case-studies/issue-1/data/templates/js/examples/universal-app/out',env=env)
    tree=git('write-tree',env=env)
    for key,fmt in [('NAME','%an'),('EMAIL','%ae'),('DATE','%aI')]:env['GIT_AUTHOR_'+key]=git('show','-s','--format='+fmt,commit)
    message=git('show','-s','--format=%B',commit)
    revised=git('commit-tree',tree,'-p',parent,input=message+'\n',env=env)
    records.append({'original':git('rev-parse',commit),'corrected':revised,'message':message})
    parent=revised
    index.unlink()
git('update-ref','refs/heads/issue-23-addf7c5fd0d4',parent,old)
git('read-tree',parent)
import json
Path('dev/log/issues/23/pulls/24/unpublished-commit-correction.json').write_text(json.dumps({'preserved_local_ref':'refs/issue-23/unpublished-build-output','records':records},indent=2)+'\n')
p=Path('.gitignore');p.write_text(p.read_text()+'\n# Electron Forge packages are generated build artifacts.\n**/out/\n')
print('Removed generated Electron binaries from unpublished commits; source history and original local records preserved.')
