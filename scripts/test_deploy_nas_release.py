import importlib.util
import pathlib
import subprocess
import sys
import tempfile
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts" / "deploy_nas_release.py"
SPEC = importlib.util.spec_from_file_location("deploy_nas_release", str(SCRIPT))
DEPLOY = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = DEPLOY
SPEC.loader.exec_module(DEPLOY)


def git(cwd, *args):
    result = subprocess.run(
        ["git", "-C", str(cwd), *args],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=True,
    )
    return result.stdout.decode().strip()


class DeployClassificationTests(unittest.TestCase):
    def test_text_line_endings_are_normalized(self):
        old = DEPLOY.GitFile("100644", "old", b"one\ntwo\n")
        new = DEPLOY.GitFile("100644", "new", b"one\nchanged\n")
        self.assertEqual(DEPLOY.classify(b"one\r\ntwo\r\n", old, new), "write")

    def test_already_deployed_file_is_idempotent(self):
        old = DEPLOY.GitFile("100644", "old", b"old")
        new = DEPLOY.GitFile("100644", "new", b"new")
        self.assertEqual(DEPLOY.classify(b"new", old, new), "already")

    def test_unknown_live_change_stops(self):
        old = DEPLOY.GitFile("100644", "old", b"old")
        new = DEPLOY.GitFile("100644", "new", b"new")
        with self.assertRaises(DEPLOY.DeployError):
            DEPLOY.classify(b"nas-only edit", old, new)

    def test_new_file_is_created_only_when_absent_or_identical(self):
        new = DEPLOY.GitFile("100644", "new", b"new")
        self.assertEqual(DEPLOY.classify(None, None, new), "write")
        self.assertEqual(DEPLOY.classify(b"new", None, new), "already")
        with self.assertRaises(DEPLOY.DeployError):
            DEPLOY.classify(b"different existing file", None, new)

    def test_deletion_requires_old_contents(self):
        old = DEPLOY.GitFile("100644", "old", b"old")
        self.assertEqual(DEPLOY.classify(b"old", old, None), "delete")
        self.assertEqual(DEPLOY.classify(None, old, None), "already")
        with self.assertRaises(DEPLOY.DeployError):
            DEPLOY.classify(b"nas-only edit", old, None)

    def test_production_data_paths_are_blocked_and_helper_files_stay_in_checkout(self):
        for path in (
            ".env",
            ".env.local",
            "public/.env.example",
            "uploads/birth/record.pdf",
            "logs/php_errors.log",
            "#recycle/file.pdf",
            "@eaDir/thumb.jpg",
        ):
            with self.subTest(path=path):
                with self.assertRaises(DEPLOY.DeployError):
                    DEPLOY.safe_relative_path(path)
        self.assertEqual(DEPLOY.safe_relative_path(".env.example"), (".env.example",))
        self.assertTrue(DEPLOY.is_repo_only(".env.example"))
        self.assertTrue(DEPLOY.is_repo_only(".env.production"))
        self.assertTrue(DEPLOY.is_repo_only(".env.synology"))
        self.assertTrue(DEPLOY.is_repo_only("database/create_marriage_table.sql"))
        self.assertTrue(DEPLOY.is_repo_only("database_schema.sql"))
        self.assertEqual(DEPLOY.safe_relative_path("public/iscan_db (1).sql"), ("public", "iscan_db (1).sql"))
        self.assertTrue(DEPLOY.is_repo_only("NAS_DEPLOYMENT_GUIDE.md"))
        self.assertTrue(DEPLOY.is_repo_only("scripts/deploy_nas_release.py"))
        self.assertTrue(DEPLOY.is_repo_only("scripts/test_deploy_nas_release.py"))
        self.assertTrue(DEPLOY.is_repo_only("docker/iscan-libreoffice/Dockerfile"))
        self.assertTrue(DEPLOY.is_repo_only("tests/example.php"))
        self.assertTrue(DEPLOY.same_repo_only_text(b"FROM debian\r\n", b"FROM debian"))
        self.assertFalse(DEPLOY.same_repo_only_text(b"FROM ubuntu\n", b"FROM debian\n"))

    def test_unrelated_local_paths_are_allowed_unless_they_overlap_incoming_paths(self):
        local_paths = [
            "#recycle/release_old/uploads/record.pdf",
            "release_old/uploads/record.pdf",
            "api/nas_only.php",
        ]
        self.assertEqual(
            DEPLOY.local_path_conflicts(local_paths, ["assets/new.js", "includes/feature.php"]),
            [],
        )
        self.assertEqual(
            DEPLOY.local_path_conflicts(["assets/new.js"], ["assets/new.js"]),
            ["assets/new.js"],
        )
        self.assertEqual(
            DEPLOY.local_path_conflicts(["assets"], ["assets/new.js"]),
            ["assets"],
        )
        self.assertEqual(
            DEPLOY.local_path_conflicts(["assets/new.js/child"], ["assets/new.js"]),
            ["assets/new.js/child"],
        )


class LiveEntrypointTests(unittest.TestCase):
    def test_accepts_public_document_root(self):
        with tempfile.TemporaryDirectory() as temporary:
            live = pathlib.Path(temporary)
            (live / "public").mkdir()
            (live / "public" / "index.php").write_text("<?php", encoding="utf-8")
            self.assertTrue(DEPLOY.has_live_entrypoint(live))

    def test_accepts_root_document_root_used_by_nas_release(self):
        with tempfile.TemporaryDirectory() as temporary:
            live = pathlib.Path(temporary)
            (live / "index.php").write_text("<?php", encoding="utf-8")
            self.assertTrue(DEPLOY.has_live_entrypoint(live))

    def test_rejects_missing_entrypoint(self):
        with tempfile.TemporaryDirectory() as temporary:
            live = pathlib.Path(temporary)
            self.assertFalse(DEPLOY.has_live_entrypoint(live))


class DeployPlanIntegrationTests(unittest.TestCase):
    def test_plan_includes_new_file_and_refuses_unexpected_live_change(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            repo = root / "repo"
            live = root / "live"
            repo.mkdir()
            live.mkdir()
            git(repo, "init")
            git(repo, "checkout", "-b", "main")
            git(repo, "config", "user.email", "deploy-test@example.invalid")
            git(repo, "config", "user.name", "Deploy Test")

            (repo / "includes").mkdir()
            (repo / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
            git(repo, "add", "includes/feature.php")
            git(repo, "commit", "-m", "base")
            base = git(repo, "rev-parse", "HEAD")

            (repo / "includes" / "feature.php").write_text("new\n", encoding="utf-8")
            (repo / "assets").mkdir()
            (repo / "assets" / "new.js").write_text("window.ready = true;\n", encoding="utf-8")
            git(repo, "add", "includes/feature.php", "assets/new.js")
            git(repo, "commit", "-m", "feature")
            target = git(repo, "rev-parse", "HEAD")

            (live / "includes").mkdir()
            (live / "includes" / "feature.php").write_bytes(b"old\r\n")
            operations = DEPLOY.plan_operations(repo, live, base, target)
            self.assertEqual({item.path: item.action for item in operations}, {
                "assets/new.js": "write",
                "includes/feature.php": "write",
            })

            (live / "includes" / "feature.php").write_bytes(b"nas-only edit")
            with self.assertRaises(DEPLOY.DeployError):
                DEPLOY.plan_operations(repo, live, base, target)

            dump = repo / "public" / "iscan_db (1).sql"
            dump.parent.mkdir(parents=True, exist_ok=True)
            dump.write_text("sensitive dump", encoding="utf-8")
            git(repo, "add", "public/iscan_db (1).sql")
            git(repo, "commit", "-m", "add database dump")
            dump_target = git(repo, "rev-parse", "HEAD")
            with self.assertRaisesRegex(DEPLOY.DeployError, "may only be deleted"):
                DEPLOY.plan_operations(repo, live, target, dump_target)

    def make_nas_and_remote(self, root, initial_files=None):
        source = root / "source"
        remote = root / "remote.git"
        nas_repo = root / "nas-repo"
        source.mkdir()
        git(source, "init")
        git(source, "checkout", "-b", "main")
        git(source, "config", "user.email", "deploy-test@example.invalid")
        git(source, "config", "user.name", "Deploy Test")
        (source / "includes").mkdir()
        (source / "public").mkdir()
        (source / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
        (source / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")
        initial_files = initial_files or {}
        for relative, content in initial_files.items():
            extra = source / pathlib.PurePosixPath(relative)
            extra.parent.mkdir(parents=True, exist_ok=True)
            extra.write_bytes(content)
        git(source, "add", "includes/feature.php", "public/index.php", *initial_files.keys())
        git(source, "commit", "-m", "baseline")

        subprocess.run(["git", "init", "--bare", str(remote)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
        git(source, "remote", "add", "origin", str(remote))
        git(source, "push", "-u", "origin", "main")
        subprocess.run(["git", "--git-dir", str(remote), "symbolic-ref", "HEAD", "refs/heads/main"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
        subprocess.run(["git", "clone", str(remote), str(nas_repo)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
        git(nas_repo, "config", "user.email", "deploy-test@example.invalid")
        git(nas_repo, "config", "user.name", "Deploy Test")
        return source, nas_repo

    def test_full_deploy_backs_up_updates_and_fast_forwards(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(root)
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("secret stays here", encoding="utf-8")
            (live / "includes" / "feature.php").write_bytes(b"old\r\n")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")

            # NAS recycle bins, old releases, backups, and unrelated source
            # files remain untouched and must not block a safe update.
            for relative in (
                "#recycle/release_old/uploads/record.pdf",
                "release_old/uploads/record.pdf",
                "backups/older-backup.tgz",
                "api/nas_only.php",
            ):
                extra = nas_repo / pathlib.PurePosixPath(relative)
                extra.parent.mkdir(parents=True, exist_ok=True)
                extra.write_bytes(b"NAS-only data")
            (nas_repo / "public" / "index.php").write_text("<?php // local checkout customization\n", encoding="utf-8")

            (source / "includes" / "feature.php").write_text("new\n", encoding="utf-8")
            (source / "assets").mkdir()
            (source / "assets" / "new.js").write_text("window.ready = true;\n", encoding="utf-8")
            git(source, "add", "includes/feature.php", "assets/new.js")
            git(source, "commit", "-m", "feature")
            git(source, "push", "origin", "main")
            expected_commit = git(source, "rev-parse", "HEAD")

            self.assertEqual(DEPLOY.run_deployment(nas_repo, live, backups), 0)
            self.assertEqual(git(nas_repo, "rev-parse", "HEAD"), expected_commit)
            self.assertEqual((live / "includes" / "feature.php").read_bytes(), b"new\n")
            self.assertTrue((live / "assets" / "new.js").is_file())
            self.assertEqual((live / ".env").read_text(encoding="utf-8"), "secret stays here")
            archives = list(backups.glob("*.tar.gz"))
            self.assertEqual(len(archives), 1)
            with DEPLOY.tarfile.open(str(archives[0]), "r:gz") as archive:
                self.assertIn("includes/feature.php", archive.getnames())
            self.assertEqual((nas_repo / "#recycle" / "release_old" / "uploads" / "record.pdf").read_bytes(), b"NAS-only data")
            self.assertEqual((nas_repo / "api" / "nas_only.php").read_bytes(), b"NAS-only data")
            self.assertIn("local checkout customization", (nas_repo / "public" / "index.php").read_text(encoding="utf-8"))

    def test_full_deploy_stops_when_untracked_file_collides_with_incoming_file(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(root)
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("secret", encoding="utf-8")
            (live / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")

            (source / "assets").mkdir()
            (source / "assets" / "new.js").write_text("window.ready = true;\n", encoding="utf-8")
            git(source, "add", "assets/new.js")
            git(source, "commit", "-m", "add asset")
            git(source, "push", "origin", "main")
            old_checkout = git(nas_repo, "rev-parse", "HEAD")

            collision = nas_repo / "assets" / "new.js"
            collision.parent.mkdir(parents=True)
            collision.write_text("NAS-only file", encoding="utf-8")

            with self.assertRaisesRegex(DEPLOY.DeployError, "conflict with incoming"):
                DEPLOY.run_deployment(nas_repo, live, backups)
            self.assertEqual(git(nas_repo, "rev-parse", "HEAD"), old_checkout)
            self.assertFalse((live / "assets" / "new.js").exists())
            self.assertFalse(backups.exists())

    def test_repo_only_file_with_missing_final_newline_is_safely_adopted(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(root)
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("secret stays here", encoding="utf-8")
            (live / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")

            dockerfile = (
                b"FROM debian:bookworm-slim\n\n"
                b"ENV HOME=/tmp\n\n"
                b"RUN apt-get update \\\n"
                b"    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \\\n"
                b"        libreoffice-writer \\\n"
                b"        fonts-dejavu \\\n"
                b"        fonts-liberation \\\n"
                b"        fontconfig \\\n"
                b"    && rm -rf /var/lib/apt/lists/*\n\n"
                b"ENTRYPOINT [\"soffice\"]\n"
            )
            dockerfile_path = source / "docker" / "iscan-libreoffice" / "Dockerfile"
            dockerfile_path.parent.mkdir(parents=True)
            dockerfile_path.write_bytes(dockerfile)
            git(source, "add", "docker/iscan-libreoffice/Dockerfile")
            git(source, "commit", "-m", "add LibreOffice image definition")
            git(source, "push", "origin", "main")
            expected_commit = git(source, "rev-parse", "HEAD")

            local_dockerfile = nas_repo / "docker" / "iscan-libreoffice" / "Dockerfile"
            local_dockerfile.parent.mkdir(parents=True)
            local_dockerfile.write_bytes(dockerfile[:-1])

            self.assertEqual(DEPLOY.run_deployment(nas_repo, live, backups), 0)
            self.assertEqual(git(nas_repo, "rev-parse", "HEAD"), expected_commit)
            self.assertEqual(DEPLOY.normalized(local_dockerfile.read_bytes()), DEPLOY.normalized(dockerfile))
            self.assertFalse((live / "docker" / "iscan-libreoffice" / "Dockerfile").exists())
            preserved = list(backups.glob("iscan-checkout-before-*.bak"))
            self.assertEqual(len(preserved), 1)
            self.assertEqual(preserved[0].read_bytes(), dockerfile[:-1])

    def test_different_untracked_repo_only_file_still_stops(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(root)
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("secret", encoding="utf-8")
            (live / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")

            remote_dockerfile = source / "docker" / "iscan-libreoffice" / "Dockerfile"
            remote_dockerfile.parent.mkdir(parents=True)
            remote_dockerfile.write_text("FROM debian:bookworm-slim\n", encoding="utf-8")
            git(source, "add", "docker/iscan-libreoffice/Dockerfile")
            git(source, "commit", "-m", "add LibreOffice image definition")
            git(source, "push", "origin", "main")
            old_checkout = git(nas_repo, "rev-parse", "HEAD")

            local_dockerfile = nas_repo / "docker" / "iscan-libreoffice" / "Dockerfile"
            local_dockerfile.parent.mkdir(parents=True)
            local_dockerfile.write_text("FROM alpine:latest\n", encoding="utf-8")

            with self.assertRaisesRegex(DEPLOY.DeployError, "conflict with incoming"):
                DEPLOY.run_deployment(nas_repo, live, backups)
            self.assertEqual(git(nas_repo, "rev-parse", "HEAD"), old_checkout)
            self.assertEqual(local_dockerfile.read_text(encoding="utf-8"), "FROM alpine:latest\n")
            self.assertFalse((live / "docker" / "iscan-libreoffice" / "Dockerfile").exists())
            self.assertFalse(backups.exists())

    def test_deleted_environment_templates_are_backed_up_but_never_deployed(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(
                root,
                {
                    ".env.production": b"DB_PASS=nas-private-production-template\n",
                    ".env.synology": b"DB_PASS=nas-private-synology-template\n",
                    "public/iscan_db (1).sql": b"nas-private-database-dump\n",
                },
            )
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("live-secret-remains", encoding="utf-8")
            (live / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")
            (live / "public" / "iscan_db (1).sql").write_bytes(b"nas-private-database-dump\n")

            git(source, "rm", ".env.production", ".env.synology", "public/iscan_db (1).sql")
            git(source, "commit", "-m", "remove tracked environment templates and public dump")
            git(source, "push", "origin", "main")

            self.assertEqual(DEPLOY.run_deployment(nas_repo, live, backups), 0)
            self.assertFalse((nas_repo / ".env.production").exists())
            self.assertFalse((nas_repo / ".env.synology").exists())
            self.assertFalse((nas_repo / "public" / "iscan_db (1).sql").exists())
            self.assertEqual((live / ".env").read_text(encoding="utf-8"), "live-secret-remains")
            self.assertFalse((live / ".env.production").exists())
            self.assertFalse((live / ".env.synology").exists())
            self.assertFalse((live / "public" / "iscan_db (1).sql").exists())
            saved = list(backups.glob("iscan-env-template-before-*.bak"))
            self.assertEqual(len(saved), 2)
            saved_contents = {DEPLOY.normalized(item.read_bytes()) for item in saved}
            self.assertEqual(
                saved_contents,
                {
                    b"DB_PASS=nas-private-production-template\n",
                    b"DB_PASS=nas-private-synology-template\n",
                },
            )
            archives = list(backups.glob("*.tar.gz"))
            self.assertEqual(len(archives), 1)
            with DEPLOY.tarfile.open(str(archives[0]), "r:gz") as archive:
                self.assertEqual(
                    archive.extractfile("public/iscan_db (1).sql").read(),
                    b"nas-private-database-dump\n",
                )

    def test_full_deploy_stops_when_tracked_local_file_overlaps_incoming_file(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(root)
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("secret", encoding="utf-8")
            (live / "includes" / "feature.php").write_text("old\n", encoding="utf-8")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")

            (source / "includes" / "feature.php").write_text("upstream change\n", encoding="utf-8")
            git(source, "add", "includes/feature.php")
            git(source, "commit", "-m", "update feature")
            git(source, "push", "origin", "main")
            old_checkout = git(nas_repo, "rev-parse", "HEAD")
            (nas_repo / "includes" / "feature.php").write_text("NAS-only tracked edit\n", encoding="utf-8")

            with self.assertRaisesRegex(DEPLOY.DeployError, "conflict with incoming"):
                DEPLOY.run_deployment(nas_repo, live, backups)
            self.assertEqual(git(nas_repo, "rev-parse", "HEAD"), old_checkout)
            self.assertEqual((live / "includes" / "feature.php").read_text(encoding="utf-8"), "old\n")
            self.assertFalse(backups.exists())

    def test_full_deploy_stops_on_live_only_change_without_writing(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            source, nas_repo = self.make_nas_and_remote(root)
            live = nas_repo / "release_blue_candidate_20260930"
            backups = root / "backups"
            (live / "includes").mkdir(parents=True)
            (live / "public").mkdir()
            (live / ".env").write_text("secret", encoding="utf-8")
            (live / "includes" / "feature.php").write_text("NAS-only patch", encoding="utf-8")
            (live / "public" / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")

            (source / "includes" / "feature.php").write_text("new\n", encoding="utf-8")
            git(source, "add", "includes/feature.php")
            git(source, "commit", "-m", "feature")
            git(source, "push", "origin", "main")
            old_checkout = git(nas_repo, "rev-parse", "HEAD")

            with self.assertRaises(DEPLOY.DeployError):
                DEPLOY.run_deployment(nas_repo, live, backups)
            self.assertEqual((live / "includes" / "feature.php").read_text(encoding="utf-8"), "NAS-only patch")
            self.assertEqual(git(nas_repo, "rev-parse", "HEAD"), old_checkout)
            self.assertFalse(backups.exists())


if __name__ == "__main__":
    unittest.main()
