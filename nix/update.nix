{
  gnused,
  go,
  gomod2nix,
  lib,
  prefetch-npm-deps,
  writers,
}:
writers.writeBashBin "update.sh" {
  makeWrapperArgs = [
    "--prefix PATH : ${lib.makeBinPath [
      gnused
      go
      gomod2nix
      prefetch-npm-deps
    ]}"
  ];
} ''
  set -euo pipefail

  npm_deps_hash=$(prefetch-npm-deps package-lock.json)
  sed -i "s|npmDepsHash = \"sha256-.*\";|npmDepsHash = \"$npm_deps_hash\";|" nix/package.nix

  # Generate from copies so go mod download cannot touch go-vod/go.sum
  workdir=$(mktemp -d)
  trap 'rm -rf "$workdir"' EXIT

  install -m644 go-vod/go.mod go-vod/go.sum "$workdir/"
  gomod2nix generate --dir "$workdir"
  install -m644 "$workdir/gomod2nix.toml" nix/gomod2nix.toml
''
