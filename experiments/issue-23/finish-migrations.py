from pathlib import Path
import json
root = Path('docs/case-studies/issue-1/data/templates')
p = root/'csharp/global.json'
data=json.loads(p.read_text()); data['test']={'runner':'Microsoft.Testing.Platform'}; p.write_text(json.dumps(data,indent=2)+'\n')
p=root/'csharp/tests/MyPackage.Tests/MyPackage.Tests.csproj'
s=p.read_text(); start=s.index('    <PackageReference Include="coverlet.collector"'); end=s.index('\n  </ItemGroup>',start)
s=s[:start]+'''    <PackageReference Include="coverlet.MTP" Version="10.1.0" PrivateAssets="all" />
    <PackageReference Include="xunit.v3" Version="4.0.3" />'''+s[end:]
s=s.replace('    <OutputType>Exe</OutputType>', '    <OutputType>Exe</OutputType>\n    <UseMicrosoftTestingPlatformRunner>true</UseMicrosoftTestingPlatformRunner>')
p.write_text(s)
for p in [Path('.github/workflows/templates.yml'), root/'csharp/.github/workflows/release.yml']:
 s=p.read_text().replace('--blame-hang-timeout 60s --collect:"XPlat Code Coverage"', '--timeout 60s --coverlet --coverlet-output-format cobertura').replace('--verbosity normal --collect:"XPlat Code Coverage"', '--timeout 60s --coverlet --coverlet-output-format cobertura').replace('dotnet docfx docs/docfx.json', 'dotnet docfx docfx.json').replace('.projects[].frameworks[] |', '.projects[].frameworks[]? |'); p.write_text(s)
p=root/'python/scripts/format_release_notes.py'; s=p.read_text().replace('import sys\n','import sys\nfrom pathlib import Path\n'); p.write_text(s)
p=root/'js/.jscpd.json'; data=json.loads(p.read_text()); data.pop('skipComments'); data.pop('format'); data['mode']='weak'; p.write_text(json.dumps(data,indent=2)+'\n')
p=root/'js/.changeset/config.json'; s=p.read_text().replace('@changesets/config@3.1.1','@changesets/config@4.0.1'); p.write_text(s)
