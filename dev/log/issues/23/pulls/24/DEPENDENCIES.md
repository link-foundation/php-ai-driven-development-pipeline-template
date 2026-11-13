# Direct dependencies and tools

Registry snapshots collected on 2026-11-13; stable releases only. Historical constraints may have several values because pins were inconsistent. Every resolved transitive is separately listed in TRANSITIVES.md. Runtime/image versions are below. Replaced tools are explicitly recorded rather than retained as unused dependencies.

| Ecosystem | Dependency | Before | Current | Latest stable | Reason / registry |
| --- | --- | --- | --- | --- | --- |
| cargo | cargo-audit | unbounded tool | 0.23.0 | 0.23.0 | Current; [registry](https://crates.io/api/v1/crates/cargo-audit) |
| cargo | cargo-llvm-cov | unbounded tool | 0.9.1 | 0.9.1 | Current; [registry](https://crates.io/api/v1/crates/cargo-llvm-cov) |
| cargo | chrono | 0.4 | 0.4.45 | 0.4.45 | Current; [registry](https://crates.io/api/v1/crates/chrono) |
| cargo | clap | 4.4 | 4.6.7 | 4.6.7 | Current; [registry](https://crates.io/api/v1/crates/clap) |
| cargo | lino-arguments | 0.3 | 0.5.0 | 0.5.0 | Current; [registry](https://crates.io/api/v1/crates/lino-arguments) |
| cargo | regex | 1 | 1.13.1 | 1.13.1 | Current; [registry](https://crates.io/api/v1/crates/regex) |
| cargo | rust-script | unbounded tool | CI uses locked Cargo pipeline; standalone invocation documented at 0.36.0 | 0.36.0 | Migration to native upstream replacement; see MIGRATIONS.md; [registry](https://crates.io/api/v1/crates/rust-script) |
| cargo | serde | 1 | 1.0.229 | 1.0.229 | Current; [registry](https://crates.io/api/v1/crates/serde) |
| cargo | serde_json | 1 | 1.0.154 | 1.0.154 | Current; [registry](https://crates.io/api/v1/crates/serde_json) |
| cargo | ureq | 2 | 3.5.2 | 3.5.2 | Current; [registry](https://crates.io/api/v1/crates/ureq) |
| cargo | walkdir | 2 | 2.5.0 | 2.5.0 | Current; [registry](https://crates.io/api/v1/crates/walkdir) |
| composer | friendsofphp/php-cs-fixer | ^3.64 | 3.95.30 | 3.95.30 | Current; [registry](https://repo.packagist.org/p2/friendsofphp/php-cs-fixer.json) |
| composer | phpstan/phpstan | ^1.12 | 2.3.5 | 2.3.5 | Current; [registry](https://repo.packagist.org/p2/phpstan/phpstan.json) |
| composer | phpunit/phpunit | ^10.5 | 13.4.7 | 13.4.7 | Current; [registry](https://repo.packagist.org/p2/phpunit/phpunit.json) |
| github | actions/cache | v5 | v6.1.0 | v6.1.0 | Current; [registry](https://api.github.com/repos/actions/cache/releases) |
| github | actions/checkout | v6, v7.0.1 | v7.0.1 | v7.0.1 | Current; [registry](https://api.github.com/repos/actions/checkout/releases) |
| github | actions/configure-pages | v6, v6.0.0 | v6.0.0 | v6.0.0 | Current; [registry](https://api.github.com/repos/actions/configure-pages/releases) |
| github | actions/dependency-review-action | v5.0.0 | v5.0.0 | v5.0.0 | Current; [registry](https://api.github.com/repos/actions/dependency-review-action/releases) |
| github | actions/deploy-pages | v5, v5.0.1 | v5.0.1 | v5.0.1 | Current; [registry](https://api.github.com/repos/actions/deploy-pages/releases) |
| github | actions/download-artifact | v7 | v8.0.2 | v8.0.2 | Current; [registry](https://api.github.com/repos/actions/download-artifact/releases) |
| github | actions/setup-dotnet | v4 | v6.1.0 | v6.1.0 | Current; [registry](https://api.github.com/repos/actions/setup-dotnet/releases) |
| github | actions/setup-java | v5 | v6.2.0 | v6.2.0 | Current; [registry](https://api.github.com/repos/actions/setup-java/releases) |
| github | actions/setup-node | v6 | v7.1.0 | v7.1.0 | Current; [registry](https://api.github.com/repos/actions/setup-node/releases) |
| github | actions/setup-python | v5 | v7.1.0 | v7.1.0 | Current; [registry](https://api.github.com/repos/actions/setup-python/releases) |
| github | actions/upload-artifact | v7, v7.0.2 | v7.0.2 | v7.0.2 | Current; [registry](https://api.github.com/repos/actions/upload-artifact/releases) |
| github | actions/upload-pages-artifact | v5, v5.0.0 | v5.0.0 | v5.0.0 | Current; [registry](https://api.github.com/repos/actions/upload-pages-artifact/releases) |
| github | android-actions/setup-android | v3 | v4.0.4 | v4.0.4 | Current; [registry](https://api.github.com/repos/android-actions/setup-android/releases) |
| github | astral-sh/ruff-pre-commit | v0.8.4 | v0.17.5 | v0.17.5 | Current; [registry](https://api.github.com/repos/astral-sh/ruff-pre-commit/releases) |
| github | astral-sh/uv | toolchain / workflow tool | 0.13.8 | 0.13.8 | Current; [registry](https://github.com/astral-sh/uv/releases/tag/0.13.8) |
| github | codecov/codecov-action | v4, v5 | v7.1.1 | v7.1.1 | Current; [registry](https://api.github.com/repos/codecov/codecov-action/releases) |
| github | denoland/deno | toolchain / workflow tool | v2.9.8 | v2.9.8 | Current; [registry](https://github.com/denoland/deno/releases/tag/v2.9.8) |
| github | denoland/setup-deno | v2 | v2.0.5 | v2.0.5 | Current; [registry](https://api.github.com/repos/denoland/setup-deno/releases) |
| github | docker/build-push-action | v7 | v7.4.0 | v7.4.0 | Current; [registry](https://api.github.com/repos/docker/build-push-action/releases) |
| github | docker/login-action | v4 | v4.8.0 | v4.8.0 | Current; [registry](https://api.github.com/repos/docker/login-action/releases) |
| github | docker/metadata-action | v6 | v6.2.0 | v6.2.0 | Current; [registry](https://api.github.com/repos/docker/metadata-action/releases) |
| github | docker/setup-buildx-action | v4 | v4.4.1 | v4.4.1 | Current; [registry](https://api.github.com/repos/docker/setup-buildx-action/releases) |
| github | dtolnay/rust-toolchain | stable | 683b588c487a69d01f6c6ddf40f036ff682cc2c8 | 683b588c487a69d01f6c6ddf40f036ff682cc2c8 | Current; [registry](https://api.github.com/repos/dtolnay/rust-toolchain/releases) |
| github | github/codeql-action | v4.39.3 | v4.39.3 | v4.39.3 | Current; [registry](https://api.github.com/repos/github/codeql-action/releases) |
| github | lycheeverse/lychee-action | v2, v2.9.0 | v2.9.0 | v2.9.0 | Current; [registry](https://api.github.com/repos/lycheeverse/lychee-action/releases) |
| github | oven-sh/bun | toolchain / workflow tool | bun-v1.4.4 | bun-v1.4.4 | Current; [registry](https://github.com/oven-sh/bun/releases/tag/bun-v1.4.4) |
| github | oven-sh/setup-bun | v2 | v2.2.0 | v2.2.0 | Current; [registry](https://api.github.com/repos/oven-sh/setup-bun/releases) |
| github | peter-evans/create-pull-request | v8 | v8.1.3 | v8.1.3 | Current; [registry](https://api.github.com/repos/peter-evans/create-pull-request/releases) |
| github | phpDocumentor/phpDocumentor | toolchain / workflow tool | v3.10.0 | v3.10.0 | Current; [registry](https://github.com/phpDocumentor/phpDocumentor/releases/tag/v3.10.0) |
| github | pre-commit/mirrors-mypy | v1.13.0 | v2.4.1 | v2.4.1 | Current; [registry](https://api.github.com/repos/pre-commit/mirrors-mypy/git/ref/tags/v2.4.1) |
| github | pre-commit/pre-commit-hooks | v5.0.0 | v6.0.0 | v6.0.0 | Current; [registry](https://api.github.com/repos/pre-commit/pre-commit-hooks/releases) |
| github | pypa/gh-action-pypi-publish | release/v1 | v1.14.3 | v1.14.3 | Current; [registry](https://api.github.com/repos/pypa/gh-action-pypi-publish/releases) |
| github | python/cpython | toolchain | v3.15.2 | v3.15.2 | Current; [registry](https://github.com/python/cpython/releases/tag/v3.15.2) |
| github | ramsey/composer-install | 4.0.0 | 4.0.0 | 4.0.0 | Current; [registry](https://api.github.com/repos/ramsey/composer-install/releases) |
| github | rhysd/actionlint | toolchain / workflow tool | v1.7.12 | v1.7.12 | Current; [registry](https://github.com/rhysd/actionlint/releases/tag/v1.7.12) |
| github | shivammathur/setup-php | 2.40.0 | 2.40.0 | 2.40.0 | Current; [registry](https://api.github.com/repos/shivammathur/setup-php/releases) |
| github | taiki-e/install-action | cargo-llvm-cov | v2.90.3 | v2.90.3 | Current; [registry](https://api.github.com/repos/taiki-e/install-action/releases) |
| github | zizmorcore/zizmor-action | v0.8.1 | v0.8.1 | v0.8.1 | Current; [registry](https://api.github.com/repos/zizmorcore/zizmor-action/releases) |
| npm | @capacitor/android | ^7.4.4 | 8.6.2 | 8.6.2 | Current; [registry](https://registry.npmjs.org/@capacitor/android/latest) |
| npm | @capacitor/cli | ^7.4.4 | 8.6.2 | 8.6.2 | Current; [registry](https://registry.npmjs.org/@capacitor/cli/latest) |
| npm | @capacitor/core | ^7.4.4 | 8.6.2 | 8.6.2 | Current; [registry](https://registry.npmjs.org/@capacitor/core/latest) |
| npm | @capacitor/ios | ^7.4.4 | 8.6.2 | 8.6.2 | Current; [registry](https://registry.npmjs.org/@capacitor/ios/latest) |
| npm | @changesets/cli | ^2.29.7 | 3.0.3 | 3.0.3 | Current; [registry](https://registry.npmjs.org/@changesets/cli/latest) |
| npm | @electron-forge/cli | ^7.8.3 | 8.0.1 | 8.0.1 | Current; [registry](https://registry.npmjs.org/@electron-forge/cli/latest) |
| npm | @electron-forge/maker-deb | ^7.8.3 | 8.0.1 | 8.0.1 | Current; [registry](https://registry.npmjs.org/@electron-forge/maker-deb/latest) |
| npm | @electron-forge/maker-rpm | ^7.8.3 | 8.0.1 | 8.0.1 | Current; [registry](https://registry.npmjs.org/@electron-forge/maker-rpm/latest) |
| npm | @electron-forge/maker-squirrel | ^7.8.3 | 8.0.1 | 8.0.1 | Current; [registry](https://registry.npmjs.org/@electron-forge/maker-squirrel/latest) |
| npm | @electron-forge/maker-zip | ^7.8.3 | 8.0.1 | 8.0.1 | Current; [registry](https://registry.npmjs.org/@electron-forge/maker-zip/latest) |
| npm | @eslint/js | implicit (imported but undeclared) | 10.0.1 | 10.0.1 | Current; [registry](https://registry.npmjs.org/@eslint/js/latest) |
| npm | @secretlint/secretlint-rule-preset-recommend | workflow tool or migration helper | 13.1.0 | 13.1.0 | Current; [registry](https://registry.npmjs.org/@secretlint/secretlint-rule-preset-recommend/latest) |
| npm | @vitejs/plugin-react | ^4.7.0 | 6.3.0 | 6.3.0 | Current; [registry](https://registry.npmjs.org/@vitejs/plugin-react/latest) |
| npm | browser-commander | workflow tool or migration helper | 0.34.1 | 0.34.1 | Current; [registry](https://registry.npmjs.org/browser-commander/latest) |
| npm | command-stream | unversioned use-m import | 2.6.0 | 2.6.0 | Current; [registry](https://registry.npmjs.org/command-stream/latest) |
| npm | electron | ^33.4.11 | 45.3.2 | 45.3.2 | Current; [registry](https://registry.npmjs.org/electron/latest) |
| npm | eslint | ^9.38.0 | 10.15.0 | 10.15.0 | Current; [registry](https://registry.npmjs.org/eslint/latest) |
| npm | eslint-config-prettier | ^10.1.8 | 10.1.8 | 10.1.8 | Current; [registry](https://registry.npmjs.org/eslint-config-prettier/latest) |
| npm | eslint-plugin-prettier | ^5.5.4 | 5.5.7 | 5.5.7 | Current; [registry](https://registry.npmjs.org/eslint-plugin-prettier/latest) |
| npm | globals | workflow tool or migration helper | 17.14.0 | 17.14.0 | Current; [registry](https://registry.npmjs.org/globals/latest) |
| npm | husky | ^9.1.7 | 9.1.7 | 9.1.7 | Current; [registry](https://registry.npmjs.org/husky/latest) |
| npm | jscpd | ^4.0.5 | 5.4.3 | 5.4.3 | Current; [registry](https://registry.npmjs.org/jscpd/latest) |
| npm | lino-arguments | unversioned use-m import | 0.4.1 | 0.4.1 | Current; [registry](https://registry.npmjs.org/lino-arguments/latest) |
| npm | lint-staged | ^16.2.6 | 17.6.0 | 17.6.0 | Current; [registry](https://registry.npmjs.org/lint-staged/latest) |
| npm | npm | workflow tool or migration helper | 12.2.2 | 12.2.2 | Current; [registry](https://registry.npmjs.org/npm/latest) |
| npm | playwright | workflow tool or migration helper | 1.65.1 | 1.65.1 | Current; [registry](https://registry.npmjs.org/playwright/latest) |
| npm | prettier | ^3.6.2 | 3.10.1 | 3.10.1 | Current; [registry](https://registry.npmjs.org/prettier/latest) |
| npm | react | ^18.3.1 | 19.3.1 | 19.3.1 | Current; [registry](https://registry.npmjs.org/react/latest) |
| npm | react-dom | ^18.3.1 | 19.3.1 | 19.3.1 | Current; [registry](https://registry.npmjs.org/react-dom/latest) |
| npm | secretlint | workflow tool or migration helper | 13.1.0 | 13.1.0 | Current; [registry](https://registry.npmjs.org/secretlint/latest) |
| npm | semver | migration candidate | 7.8.6 | 7.8.6 | Current; [registry](https://registry.npmjs.org/semver/latest) |
| npm | test-anywhere | ^0.8.48 | 0.9.1 | 0.9.1 | Current; [registry](https://registry.npmjs.org/test-anywhere/latest) |
| npm | vite | ^6.4.1 | 8.3.8 | 8.3.8 | Current; [registry](https://registry.npmjs.org/vite/latest) |
| nuget | Microsoft.NET.Test.Sdk | 17.12.0 | Removed: native MTP 2.5.3 replaces VSTest SDK | 18.12.0 | Migration to native upstream replacement; see MIGRATIONS.md; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.net.test.sdk/index.json) |
| nuget | coverlet.collector | 6.0.2 | Replaced by coverlet.MTP 10.1.0 | 10.1.0 | Migration to native upstream replacement; see MIGRATIONS.md; [registry](https://api.nuget.org/v3-flatcontainer/coverlet.collector/index.json) |
| nuget | docfx | unbounded dotnet tool | 2.81.0 | 2.81.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/docfx/index.json) |
| nuget | xunit | 2.9.2 | Replaced by xunit.v3 4.0.3 | 2.9.3 | Migration to native upstream replacement; see MIGRATIONS.md; [registry](https://api.nuget.org/v3-flatcontainer/xunit/index.json) |
| nuget | xunit.runner.visualstudio | 2.8.2 | Removed: native MTP runner replaces VS adapter | 4.0.1 | Migration to native upstream replacement; see MIGRATIONS.md; [registry](https://api.nuget.org/v3-flatcontainer/xunit.runner.visualstudio/index.json) |
| nuget | xunit.v3 | migration candidate from xunit 2 | 4.0.3 | 4.0.3 | Current; [registry](https://api.nuget.org/v3-flatcontainer/xunit.v3/index.json) |
| pypi | build | CI tool / unbounded | 1.6.1 | 1.6.1 | Current; [registry](https://pypi.org/pypi/build/json) |
| pypi | furo | >=2024.8.6 | 2025.12.19 | 2025.12.19 | Current; [registry](https://pypi.org/pypi/furo/json) |
| pypi | hatchling | unbounded | 1.33.0 | 1.33.0 | Current; [registry](https://pypi.org/pypi/hatchling/json) |
| pypi | mypy | >=1.13.0 | 2.4.1 | 2.4.1 | Current; [registry](https://pypi.org/pypi/mypy/json) |
| pypi | myst-parser | >=3.0 | 5.2.0 | 5.2.0 | Current; [registry](https://pypi.org/pypi/myst-parser/json) |
| pypi | pip | CI tool / unbounded | 26.3 | 26.3 | Current; [registry](https://pypi.org/pypi/pip/json) |
| pypi | pip-audit | CI tool / unbounded | 2.10.1 | 2.10.1 | Current; [registry](https://pypi.org/pypi/pip-audit/json) |
| pypi | pre-commit | >=4.0.0 | 4.7.0 | 4.7.0 | Current; [registry](https://pypi.org/pypi/pre-commit/json) |
| pypi | pytest | >=8.3.0 | 9.2.0 | 9.2.0 | Current; [registry](https://pypi.org/pypi/pytest/json) |
| pypi | pytest-asyncio | >=0.24.0 | 1.5.0 | 1.5.0 | Current; [registry](https://pypi.org/pypi/pytest-asyncio/json) |
| pypi | pytest-cov | >=6.0.0 | 7.1.0 | 7.1.0 | Current; [registry](https://pypi.org/pypi/pytest-cov/json) |
| pypi | ruff | >=0.8.0 | 0.17.5 | 0.17.5 | Current; [registry](https://pypi.org/pypi/ruff/json) |
| pypi | scriv | >=1.7.0 | 1.8.1 | 1.8.1 | Current; [registry](https://pypi.org/pypi/scriv/json) |
| pypi | sphinx | >=7.4 | 9.2.0 | 9.2.0 | Current; [registry](https://pypi.org/pypi/sphinx/json) |
| pypi | twine | CI tool / unbounded | 7.0.0 | 7.0.0 | Current; [registry](https://pypi.org/pypi/twine/json) |
| pypi | uv | CI tool / unbounded | 0.13.8 | 0.13.8 | Current; [registry](https://pypi.org/pypi/uv/json) |
| pypi | zizmor | 1.31.1 | 1.32.0 | 1.32.0 | Current; [registry](https://pypi.org/pypi/zizmor/json) |
| npm | @stylistic/eslint-plugin | newly declared; replaces removed ESLint core rule | 5.10.0 | 5.10.0 | Current; [registry](https://registry.npmjs.org/@stylistic/eslint-plugin/latest) |
| npm | @actions/core | newly declared / migration component | 3.0.1 | 3.0.1 | Current; [registry](https://registry.npmjs.org/@actions/core/latest) |
| npm | yaml | newly declared / migration component | 2.9.1 | 2.9.1 | Current; [registry](https://registry.npmjs.org/yaml/latest) |
| nuget | Microsoft.Testing.Extensions.Telemetry | newly declared / migration component | 2.5.3 | 2.5.3 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.testing.extensions.telemetry/index.json) |
| nuget | Microsoft.Testing.Extensions.TrxReport.Abstractions | newly declared / migration component | 2.5.3 | 2.5.3 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.testing.extensions.trxreport.abstractions/index.json) |
| nuget | Microsoft.Testing.Platform | newly declared / migration component | 2.5.3 | 2.5.3 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.testing.platform/index.json) |
| nuget | Microsoft.Testing.Platform.MSBuild | newly declared / migration component | 2.5.3 | 2.5.3 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.testing.platform.msbuild/index.json) |
| nuget | coverlet.MTP | newly declared / migration component | 10.1.0 | 10.1.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/coverlet.mtp/index.json) |
| nuget | Microsoft.Bcl.AsyncInterfaces | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.bcl.asyncinterfaces/index.json) |
| nuget | Microsoft.Extensions.Configuration | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.extensions.configuration/index.json) |
| nuget | Microsoft.Extensions.Configuration.FileExtensions | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.extensions.configuration.fileextensions/index.json) |
| nuget | Microsoft.Extensions.Configuration.Json | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.extensions.configuration.json/index.json) |
| nuget | Microsoft.Extensions.DependencyInjection | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.extensions.dependencyinjection/index.json) |
| nuget | Microsoft.Extensions.FileProviders.Physical | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.extensions.fileproviders.physical/index.json) |
| nuget | Microsoft.Extensions.FileSystemGlobbing | newly declared / migration component | 11.0.0 | 11.0.0 | Current; [registry](https://api.nuget.org/v3-flatcontainer/microsoft.extensions.filesystemglobbing/index.json) |

## Runtime, toolchain, and image pins

| Component | Before | Current | Latest stable | Evidence / reason |
| --- | --- | --- | --- | --- |
| PHP | 8.2 / 8.3 / 8.4 | 8.5.12 | 8.5.12 | php-releases.json; local 8.5.11 also tested, CI uses 8.5.12 |
| Composer | 2 | 2.11.2 | 2.11.2 | registry-extra.json |
| Node | 20 / 22 / 24 | 26.13.1 | 26.13.1 | node-releases.json |
| npm | unbounded / 11 minimum | 12.2.2 | 12.2.2 | registry-extra.json |
| Bun | latest / 1.3.14 | 1.4.4 | 1.4.4 | registry-extra.json |
| Deno | v2.x | 2.9.8 | 2.9.8 | registry-extra.json; deno-update.log |
| Python | 3.10–3.13 | 3.15.2 | 3.15.2 | registry-extra.json; Python CI |
| Rust | stable / MSRV 1.70 / edition 2021 | 1.100.0 / edition 2024 | 1.100.0 | rust-stable.toml |
| .NET SDK / TFM | 8.0.x / net8.0 | 11.0.100 / net11.0 | 11.0.100 | dotnet-releases.json |
| Temurin Java | 21 | 24.0.2+12 | 27.0.1.0.2+1 | java-27-latest.json / java-24-latest.json; Capacitor 8.6.2 template Gradle 8.14.3 supports at most Java 24. See UPSTREAM.md. |
| Ubuntu hosted image | 24.04 | 26.04 | 26.04 | runner-images.txt; actionlint label workaround documented |
| macOS hosted image | 14 | 26 | 26 | runner-images.txt |
| Windows hosted image | 2022 | 2025 | 2025 | runner-images.txt; current standard non-preview hosted image |
| Playwright container | 1.61.1-noble | v1.65.1-noble | v1.65.1-noble | package registry Playwright 1.65.1 and official image matching that release |
| phpDocumentor | 3 (floating download) | 3.10.0 | 3.10.0 | registry-extra.json |
| actionlint image | 1.7.12 digest | 1.7.12 / b1934ee5f1c509618f2508e6eb47ee0d3520686341fec936f3b79331f9315667 | 1.7.12 | registry.json; official Docker digest; actionlint-final.log |

Native OS SDKs used by the opt-in generated Android/iOS projects come from Capacitor 8.6.2 templates and the hosted runner image. No native platform source or independent Gradle/Xcode project was committed previously; this update preserves generation and sync instead of inventing a second toolchain tree.
