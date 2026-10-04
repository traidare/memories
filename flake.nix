{
  outputs = inputs:
    inputs.flake-parts.lib.mkFlake {inherit inputs;} ({lib, ...}: {
      systems = ["x86_64-linux" "aarch64-linux"];

      perSystem = {
        config,
        inputs',
        pkgs,
        ...
      }: {
        packages = {
          nextcloud-app-memories = pkgs.callPackage ./nix/package.nix {
            inherit (inputs'.gomod2nix.legacyPackages) buildGoApplication;
            go = pkgs.go_1_27;
          };
          default = config.packages.nextcloud-app-memories;
        };

        devShells.default = pkgs.mkShell {
          packages = [
            pkgs.gnumake
            pkgs.perl
            pkgs.unzip
            pkgs.wget

            (pkgs.php.withExtensions ({
              enabled,
              all,
            }:
              lib.unique (enabled
                ++ (with all; [
                  imagick
                  intl
                  mbstring
                  pdo_sqlite
                  sqlite3
                ]))))
            pkgs.phpPackages.composer
          ];
        };

        # Run with `nix build .#checks.x86_64-linux.TEST_NAME -L`
        checks = {
          memories-embedded-tags = import ./nix/tests/memories-embedded-tags.nix {
            inherit pkgs lib;
            memoriesApp = config.packages.nextcloud-app-memories;
          };
        };
      };
    });

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-parts = {
      url = "github:hercules-ci/flake-parts";
      inputs.nixpkgs-lib.follows = "nixpkgs";
    };
    gomod2nix = {
      url = "github:nix-community/gomod2nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };
  };
}
