// Small registry-independent reproductions against the installed, locked packages.
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
const require = createRequire(resolve('docs/case-studies/issue-1/data/templates/js/package.json'));
const root = mkdtempSync(join(tmpdir(), 'issue-23-repro-'));
try {
 const metadata = { name: 'issue-23-lock-repro', version: '1.0.0' };
 writeFileSync(join(root, 'package.json'), JSON.stringify(metadata));
 writeFileSync(join(root, 'package-lock.json'), JSON.stringify({ ...metadata, lockfileVersion: 3, packages: {'': metadata} }));
 mkdirSync(join(root, '.changeset'));
 writeFileSync(join(root, '.changeset/config.json'), JSON.stringify({changelog:false,commit:false,baseBranch:'main',access:'public',updateInternalDependencies:'patch',ignore:[]}));
 writeFileSync(join(root, '.changeset/repro.md'), '---\n"issue-23-lock-repro": patch\n---\n\nReproduce stale lock metadata.\n');
 execFileSync(process.execPath,[require.resolve('@changesets/cli/bin.js'),'version'],{cwd:root,stdio:'inherit'});
 const pkg=JSON.parse(readFileSync(join(root,'package.json')));
 const lock=JSON.parse(readFileSync(join(root,'package-lock.json')));
 console.log(JSON.stringify({changesets:require('@changesets/cli/package.json').version,packageVersion:pkg.version,lockVersion:lock.version,lockRootVersion:lock.packages[''].version}));
 if(pkg.version!=='1.0.1'||lock.packages[''].version!=='1.0.0') throw Error('Expected stale lockfile reproduction');
 // Published installer code: undo only our exact backport in an isolated fixture.
 const appRequire=createRequire(resolve('docs/case-studies/issue-1/data/templates/js/examples/universal-app/package.json'));
 const installerRoot=resolve(appRequire.resolve('electron-winstaller/package.json'),'..');
 const original=readFileSync(join(installerRoot,'script/select-7z-arch.js'),'utf8').replace('const arch = os.arch();','const arch = os.arch;');
 console.log('unpatched arch source: '+original.match(/const arch[^;]+;/)?.[0]);
 mkdirSync(join(root,'script'));mkdirSync(join(root,'vendor'));
 writeFileSync(join(root,'script/select-7z-arch.cjs'),original);
 for(const file of ['7z.exe','7z.dll','7z-x64.exe','7z-x64.dll','7z-ia32.exe','7z-ia32.dll'])writeFileSync(join(root,'vendor',file),'fixture');
 const paths=[];
 runInNewContext(original,{require:(name)=>name==='os'?{arch:()=> 'x64'}:{copyFileSync:(from)=>paths.push(from)},console:{log:()=>{}}});
 console.log(JSON.stringify({winstaller:appRequire('electron-winstaller/package.json').version,selected:paths}));
 if(paths.some(path=>path==='vendor/7z-x64.exe'))throw Error('Expected invalid architecture path');
} finally {rmSync(root,{recursive:true,force:true});}
