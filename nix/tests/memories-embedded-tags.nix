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
      package = pkgs.nextcloud34;
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
    environment.systemPackages = with pkgs; [imagemagick exiftool];
  };

  testScript = {nodes, ...}: ''
    import html
    import json
    import re
    import shlex
    from urllib.parse import quote, urlencode

    datadir = "${nodes.machine.services.nextcloud.datadir}/data"

    machine.wait_for_unit("mysql.service")
    machine.wait_for_unit("phpfpm-nextcloud.service")
    machine.wait_for_unit("nginx.service")
    machine.wait_until_succeeds(
        "curl -fsS http://localhost/status.php | grep -q '\"installed\":true'",
        timeout=180,
    )

    machine.succeed("nextcloud-occ app:enable memories")
    machine.succeed("curl -fsS http://localhost/login > /dev/null")
    machine.succeed("nextcloud-occ app:enable groupfolders")
    machine.succeed("OC_PASS=user1password nextcloud-occ user:add user1 --password-from-env")
    machine.succeed("OC_PASS=user2password nextcloud-occ user:add user2 --password-from-env")
    for user in ("user1", "user2"):
        machine.succeed(f"nextcloud-occ user:setting {user} memories timelinePath /")

    def login(user):
        jar = f"/tmp/{user}.cookies"
        page = machine.succeed(f"curl -fsS -c {jar} http://localhost/login")
        match = re.search(r'data-requesttoken="([^"]+)"', page)
        assert match is not None
        token = html.unescape(match.group(1))
        form = urlencode({"user": user, "password": f"{user}password", "requesttoken": token})
        status = machine.succeed(
            f"curl -sS -L -b {jar} -c {jar} -o /dev/null -w '%{{http_code}}' "
            f"-H 'Origin: http://localhost' -d {shlex.quote(form)} http://localhost/login"
        ).strip()
        root_headers = machine.succeed(f"curl -sS -b {jar} -D - -o /dev/null http://localhost/")
        assert status == "200" and f"X-User-Id: {user}" in root_headers, (user, status)
        page = machine.succeed(f"curl -fsS -b {jar} http://localhost/apps/memories/")
        match = re.search(r'data-requesttoken="([^"]+)"', page)
        assert match is not None
        return jar, html.unescape(match.group(1))

    sessions = {user: login(user) for user in ("user1", "user2")}

    def api(path, user="user1", params=None):
        url = "http://localhost/apps/memories" + path
        if params:
            url += "?" + urlencode(params)
        jar, token = sessions[user]
        header = shlex.quote("requesttoken: " + token)
        return json.loads(machine.succeed(f"curl -fsS -b {jar} -H {header} {shlex.quote(url)}"))

    assert api("/api/embedded-tags/flat")["tags"] == []
    assert api("/api/embedded-tags/hierarchical")["tags"] == []
    assert api("/api/embedded-tags/count")["count"] == 0
    anonymous_status = machine.succeed(
        "curl -sS -o /tmp/anonymous.json -w '%{http_code}'"
        " http://localhost/apps/memories/api/embedded-tags/flat"
    ).strip()
    assert anonymous_status not in ("200", "500"), anonymous_status

    machine.succeed("nextcloud-occ group:add testgroup")
    for user in ("user1", "user2"):
        machine.succeed(f"nextcloud-occ group:adduser testgroup {user}")
    folder_id = machine.succeed("nextcloud-occ groupfolders:create SharedPhotos").strip()
    machine.succeed(
        f"nextcloud-occ groupfolders:group {folder_id} testgroup read write delete share"
    )

    def create_photo(name, color, date, keywords, hierarchy=None, rating=5):
        path = f"/tmp/{name}.jpg"
        machine.succeed(f"magick -size 64x64 xc:{color} {path}")
        args = [
            "exiftool", "-overwrite_original", f"-DateTimeOriginal={date}",
            f"-Rating={rating}",
        ]
        args += [f"-Keywords={keyword}" for keyword in keywords]
        if hierarchy:
            args.append(f"-HierarchicalSubject={hierarchy}")
        args.append(path)
        machine.succeed(" ".join(shlex.quote(arg) for arg in args))
        raw = json.loads(machine.succeed(f"exiftool -j {path}"))[0]
        assert raw["DateTimeOriginal"] == date, raw
        raw_keywords = raw.get("Keywords", [])
        if isinstance(raw_keywords, str):
            raw_keywords = [raw_keywords]
        assert set(keywords) <= set(raw_keywords), raw
        return path

    shared = [
        create_photo("beach", "red", "2024:06:01 12:00:00", ["Vacation"], "Travel|Beach", 5),
        create_photo("mountain", "green", "2024:06:01 13:00:00", ["Vacation"], "Travel|Mountain", 2),
        create_photo("travelogue", "blue", "2024:06:02 12:00:00", ["Travelogue"], rating=5),
        create_photo("literal", "yellow", "2024:06:02 13:00:00", ["City, Night", "100%_!"], rating=5),
    ]
    private = create_photo("secret", "purple", "2024:06:02 14:00:00", ["Secret"], rating=5)

    shared_dir = f"{datadir}/__groupfolders/{folder_id}/files"
    machine.succeed(f"mkdir -p {shlex.quote(shared_dir)}")
    for photo in shared:
        machine.succeed(f"cp {shlex.quote(photo)} {shlex.quote(shared_dir)}")
    private_dir = f"{datadir}/user1/files"
    machine.succeed(f"cp {shlex.quote(private)} {shlex.quote(private_dir)}")
    machine.succeed(f"chown -R nextcloud:nextcloud {shlex.quote(shared_dir)} {shlex.quote(private_dir)}")
    machine.succeed("nextcloud-occ groupfolders:scan --all", timeout=120)
    machine.succeed("nextcloud-occ files:scan user1", timeout=120)
    machine.succeed("nextcloud-occ memories:index --user user1 --skip-cleanup", timeout=180)
    machine.succeed("nextcloud-occ memories:index --user user2 --skip-cleanup", timeout=180)

    def sql(query):
        return machine.succeed("mariadb nextcloud -N -e " + shlex.quote(query)).strip()

    assert int(sql("SELECT COUNT(*) FROM oc_memories")) == 5
    for user in ("user1", "user2"):
        assert int(sql(
            "SELECT COUNT(*) FROM oc_memories_embedded_tags "
            f"WHERE user_id='{user}' AND tag='Vacation'"
        )) == 1

    user1_tags = {tag["path"] for tag in api("/api/embedded-tags/flat")["tags"]}
    user2_tags = {tag["path"] for tag in api("/api/embedded-tags/flat", "user2")["tags"]}
    assert user1_tags - user2_tags == {"Secret"}, (user1_tags, user2_tags)
    assert {"Travel", "Travel/Beach", "Travel/Mountain", "Vacation"} <= user2_tags
    for user, paths in (("user1", user1_tags), ("user2", user2_tags)):
        assert api("/api/embedded-tags/count", user)["count"] == len(paths)
        page = api("/api/embedded-tags/flat", user, {"limit": 2, "offset": 1})
        assert len(page["tags"]) == 2
        assert page["pagination"]["total"] == len(paths)
        tree = api("/api/embedded-tags/hierarchical", user)["tags"]
        travel = next(node for node in tree if node["path"] == "Travel")
        assert {node["path"] for node in travel["children"]} == {
            "Travel/Beach", "Travel/Mountain",
        }
        filtered = api("/api/embedded-tags/hierarchical", user, {"pattern": "Beach"})["tags"]
        assert len(filtered) == 1 and filtered[0]["path"] == "Travel"
        assert [node["path"] for node in filtered[0]["children"]] == ["Travel/Beach"]
        pattern = api("/api/embedded-tags/flat", user, {"pattern": "100%_!"})
        assert [tag["path"] for tag in pattern["tags"]] == ["100%_!"]
        assert api("/api/embedded-tags/count", user, {"pattern": "100%_!"})["count"] == 1

    def check_days(user, tags, expected, min_rating=0):
        selected = ",".join(quote(tag, safe="") for tag in tags)
        params = {"embeddedTags": selected}
        if min_rating:
            params["minRating"] = min_rating
        days = api("/api/days", user, params)
        assert sum(int(day["count"]) for day in days) == expected, (user, tags, days)
        for day in days:
            photos = api(f"/api/days/{day['dayid']}", user, params)
            assert len(photos) == int(day["count"]), (user, tags, day, photos)

    for user in ("user1", "user2"):
        check_days(user, ["Travel"], 2)
        check_days(user, ["Travel", "Vacation"], 2)
        check_days(user, ["Travel/Beach", "Vacation"], 1)
        check_days(user, ["Travel", "Travelogue"], 0)
        check_days(user, ["Travel"], 1, min_rating=4)
        check_days(user, ["City, Night", "100%_!"], 1)
    check_days("user1", ["Secret"], 1)
    check_days("user2", ["Secret"], 0)
  '';
}
