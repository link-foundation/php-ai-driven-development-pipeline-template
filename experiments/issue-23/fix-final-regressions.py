from pathlib import Path
r=Path('docs/case-studies/issue-1/data/templates')
p=r/'rust/tests/unit/ci-cd/workflow_release.rs';p.write_text(p.read_text().replace('rust-script scripts/check-crate-size.rs','cargo run --locked --manifest-path pipeline/Cargo.toml --bin check-crate-size --'))
p=r/'js/scripts/setup-npm.mjs';s=p.read_text().replace('setupNpm($)', 'setupNpm($, nodeVersion = process.version)').replace('isSupportedNodeVersion(process.version)', 'isSupportedNodeVersion(nodeVersion)');p.write_text(s)
p=r/'js/tests/setup-npm.test.js';s=p.read_text().replace('setupNpm(command)', "setupNpm(command, 'v26.13.1')");p.write_text(s)
