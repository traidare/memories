{
  buildGoApplication,
  buildNpmPackage,
  exiftool,
  ffmpeg-headless,
  go,
  lib,
  replaceVars,
  ...
}: let
  version = builtins.head (
    builtins.elemAt
    (builtins.split "<version>([0-9.]+)</version>" (builtins.readFile ../appinfo/info.xml))
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

    npmDepsHash = "sha256-7E1QhkhBZ1GmOP+w+9qo3Grl57XD6YOTVuoeUXq5nHI=";
    makeCacheWritable = true;

    patches = [
      (replaceVars ./memories-paths.diff {
        exiftool = lib.getExe exiftool;
        ffmpeg = lib.getExe ffmpeg-headless;
        ffprobe = lib.getExe' ffmpeg-headless "ffprobe";
        go-vod = lib.getExe go-vod;
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
      cp -r ./* $out/
    '';

    meta = {
      description = "Fast, modern and advanced photo management suite";
      homepage = "https://apps.nextcloud.com/apps/memories";
      changelog = "https://github.com/pulsejet/memories/blob/v${version}/CHANGELOG.md";
      license = lib.licenses.agpl3Only;
    };
  }
