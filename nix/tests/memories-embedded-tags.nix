# Test: Multi-user embedded tags visibility.
#
# Verifies that when two users share a group folder, both get entries
# in oc_memories_embedded_tags after indexing — even though oc_memories
# only stores one row per file (file-scoped).
{
  lib,
  memoriesApp,
  pkgs,
}:
pkgs.testers.runNixOSTest {
  name = "memories-embedded-tags";

  nodes.machine = {config, ...}: {
    services.nextcloud = {
      enable = true;
      package = pkgs.nextcloud32;
      hostName = "localhost";

      config = {
        adminuser = "admin";
        adminpassFile = "${pkgs.writeText "admin-pass" "testadminpass123"}";
        dbtype = "mysql";
      };

      database.createLocally = true;

      extraApps = {
        memories = memoriesApp;
        inherit (config.services.nextcloud.package.packages.apps) groupfolders;
      };
    };

    virtualisation.memorySize = 3072;
    virtualisation.diskSize = 8192;

    environment.systemPackages = with pkgs; [
      imagemagick
      exiftool
    ];
  };

  testScript = {nodes, ...}: ''
    machine.wait_for_unit("mysql.service")
    machine.wait_for_unit("phpfpm-nextcloud.service")
    machine.wait_for_unit("nginx.service")

    # Wait for Nextcloud to respond
    machine.wait_until_succeeds(
        "curl -sfk http://localhost/status.php"
        " | grep -q '\"installed\":true'",
        timeout=180,
    )

    # Enable apps
    machine.succeed("nextcloud-occ app:enable memories")
    machine.succeed("nextcloud-occ app:enable groupfolders")

    # Create users
    machine.succeed("OC_PASS=user1password nextcloud-occ user:add user1 --password-from-env")
    machine.succeed("OC_PASS=user2password nextcloud-occ user:add user2 --password-from-env")

    # Create group and add users to it
    machine.succeed("nextcloud-occ group:add testgroup")
    machine.succeed("nextcloud-occ group:adduser testgroup user1")
    machine.succeed("nextcloud-occ group:adduser testgroup user2")

    # Create group folder and assign group with full permissions
    folder_id = machine.succeed(
        "nextcloud-occ groupfolders:create SharedPhotos"
    ).strip()
    machine.succeed(
        f"nextcloud-occ groupfolders:group {folder_id} testgroup read write delete share"
    )

    # Create a minimal JPEG with EXIF keyword tags
    machine.succeed("magick -size 100x100 xc:red /tmp/test-photo.jpg")
    machine.succeed(
        "exiftool -overwrite_original"
        " -Keywords=Vacation -Keywords=Beach"
        " '-HierarchicalSubject=Travel|Beach'"
        " /tmp/test-photo.jpg"
    )

    # Place the image in the group folder's data directory
    datadir = "${nodes.machine.services.nextcloud.datadir}/data"
    groupfolder_path = f"{datadir}/__groupfolders/{folder_id}/files"
    machine.succeed(f"mkdir -p {groupfolder_path}")
    machine.succeed(f"cp /tmp/test-photo.jpg {groupfolder_path}/test-photo.jpg")
    #machine.succeed(f"chown -R nextcloud:nextcloud {groupfolder_path}")

    # Rescan groupfolders
    machine.succeed("nextcloud-occ groupfolders:scan --all", timeout=120)

    # --- Index user1 (first indexer — file gets added to oc_memories) ---
    machine.succeed(
        "nextcloud-occ memories:index --user user1 --skip-cleanup",
        timeout=120,
    )

    def sql(query):
        """Run a SQL query and return the stripped output."""
        return machine.succeed(
            f"mariadb nextcloud -N -e \"{query}\""
        ).strip()

    # Verify the file was indexed
    memories_count = sql("SELECT COUNT(*) FROM oc_memories")
    assert memories_count == "1", (
        f"Expected 1 entry in oc_memories, got {memories_count}"
    )

    # Verify user1 has embedded tag entries
    user1_tags = int(sql(
        "SELECT COUNT(*) FROM oc_memories_embedded_tags WHERE user_id='user1'"
    ))
    assert user1_tags > 0, f"user1 should have embedded tags, got {user1_tags}"

    # --- Index user2 (file already in oc_memories — the fix creates tags for user2) ---
    machine.succeed(
        "nextcloud-occ memories:index --user user2 --skip-cleanup",
        timeout=120,
    )

    # Verify user2 also has embedded tag entries
    user2_tags = int(sql(
        "SELECT COUNT(*) FROM oc_memories_embedded_tags WHERE user_id='user2'"
    ))
    assert user2_tags > 0, (
        f"user2 should have embedded tags (bug fix), got {user2_tags}"
    )

    # Verify both users have the same number of tags
    assert user1_tags == user2_tags, (
        f"Tag counts differ: user1={user1_tags}, user2={user2_tags}"
    )

    # Verify oc_memories still has exactly 1 entry (file-scoped, not duplicated)
    memories_count = sql("SELECT COUNT(*) FROM oc_memories")
    assert memories_count == "1", (
        f"Expected 1 entry in oc_memories after both indexes, got {memories_count}"
    )

    # Verify specific tag content for both users
    user1_vacation = sql(
        "SELECT COUNT(*) FROM oc_memories_embedded_tags"
        " WHERE user_id='user1' AND tag='Vacation'"
    )
    user2_vacation = sql(
        "SELECT COUNT(*) FROM oc_memories_embedded_tags"
        " WHERE user_id='user2' AND tag='Vacation'"
    )
    assert user1_vacation == "1", "user1 missing 'Vacation' tag"
    assert user2_vacation == "1", "user2 missing 'Vacation' tag"
  '';
}
