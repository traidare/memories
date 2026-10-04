{
  buildGoApplication,
  buildNpmPackage,
  exiftool,
  ffmpeg-headless,
  go,
  lib,
  perl,
  replaceVars,
  ...
}: let
  version = builtins.head (
    builtins.elemAt
    (builtins.split "<version>([^<]+)</version>" (builtins.readFile ../appinfo/info.xml))
    1
  );

  go-vod = buildGoApplication {
    pname = "go-vod";
    inherit version;
    inherit go;
    src = ../go-vod;
    modules = ./gomod2nix.toml;
    CGO_ENABLED = 0;
    meta = {
      description = "Extremely minimal on-demand video transcoding server in go";
      mainProgram = "go-vod";
      license = lib.licenses.agpl3Only;
    };
  };
in
  buildNpmPackage {
    pname = "nextcloud-app-memories";
    inherit version;

    src = ../.;

    npmDepsHash = "sha256-Cd9b8dU7vMs9WRNelBG/ifJYEj69FWzsMHnrBceFk40=";
    makeCacheWritable = true;

    patches = [
      (replaceVars ./memories-paths.diff {
        exiftool = lib.getExe exiftool;
        ffmpeg = lib.getExe ffmpeg-headless;
        ffprobe = lib.getExe' ffmpeg-headless "ffprobe";
        go-vod = lib.getExe go-vod;
        perl = lib.getExe perl;
      })
    ];

    postPatch = ''
      rm -f appinfo/signature.json
      rm -rf bin-ext/

      sed -i 's/EXIFTOOL_VER = .*/EXIFTOOL_VER = @;/' lib/Service/BinExt.php
      substituteInPlace lib/Service/BinExt.php \
        --replace-fail "EXIFTOOL_VER = @" "EXIFTOOL_VER = '${exiftool.version}'"
    '';

    dontNpmInstall = true;

    installPhase = ''
      mkdir -p $out
      cp -r appinfo l10n img js lib templates COPYING README.md CHANGELOG.md exiftest* composer* $out/
      test ! -e $out/node_modules
      test ! -e $out/bin-ext
      test -z "$(find $out -name '*.orig' -print -quit)"
    '';

    meta = {
      description = "Fast, modern and advanced photo management suite";
      homepage = "https://apps.nextcloud.com/apps/memories";
      changelog = "https://github.com/pulsejet/memories/blob/v${version}/CHANGELOG.md";
      license = lib.licenses.agpl3Only;
    };
  }
