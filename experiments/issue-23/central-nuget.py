from pathlib import Path
import json,re
r=Path('docs/case-studies/issue-1/data/templates/csharp')
p=r/'tests/MyPackage.Tests/MyPackage.Tests.csproj';s=p.read_text();versions={m[1]:m[2] for m in re.finditer(r'<PackageReference Include="([^"]+)" Version="([^"]+)"',s)};s=re.sub(r'(<PackageReference Include="[^"]+") Version="[^"]+"',r'\1',s);p.write_text(s)
d=json.loads((r/'tests/MyPackage.Tests/packages.lock.json').read_text());reg=json.loads(Path('dev/log/issues/23/pulls/24/transitive-registry.json').read_text())
for n,v in next(iter(d['dependencies'].values())).items():
 if n.startswith('Microsoft.Testing.') and 'resolved'in v:versions[n]=reg['nuget:'+n]['latest']
(r/'Directory.Packages.props').write_text('''<Project>
  <PropertyGroup>
    <ManagePackageVersionsCentrally>true</ManagePackageVersionsCentrally>
    <CentralPackageTransitivePinningEnabled>true</CentralPackageTransitivePinningEnabled>
  </PropertyGroup>
  <ItemGroup>
'''+''.join(f'    <PackageVersion Include="{n}" Version="{v}" />\n'for n,v in sorted(versions.items()))+'''  </ItemGroup>
</Project>
''')
