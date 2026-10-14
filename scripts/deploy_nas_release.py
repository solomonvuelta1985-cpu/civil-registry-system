#!/usr/bin/env python3
"""Fail-closed deployment from the NAS Git checkout to its live release.

Run as root on the NAS from any working directory. The script fetches
origin/main, compares only changed tracked paths against the live release,
backs up affected live files, applies files that match the old baseline (or
are already at the target version), and fast-forwards the checkout only after
the live update succeeds. It never runs database migrations.
"""

from __future__ import annotations

import dataclasses
import datetime as _datetime
import io
import os
import pathlib
import stat
import subprocess
import sys
import tarfile
import tempfile
from typing import List, Optional, Sequence, Tuple


DEFAULT_REPO = "/volume1/iscan"
DEFAULT_LIVE = "/volume1/iscan/release_blue_candidate_20260930"
DEFAULT_BACKUPS = "/volume1/iscan/backups"
REMOTE_REF = "origin/main"

# This script is an operations tool in the Git checkout, not a website file.
REPO_ONLY_PATHS = {
    "NAS_DEPLOYMENT_GUIDE.md",
    "scripts/deploy_nas_release.py",
    "scripts/test_deploy_nas_release.py",
}
REPO_ONLY_PREFIXES = ("tests/",)
PROTECTED_SEGMENTS = {
    ".git",
    ".claude",
    ".codex",
    ".agents",
    "#recycle",
    "@eaDir",
    "uploads",
    "logs",
    "storage",
    "tmp",
    "backups",
}


class DeployError(RuntimeError):
    """A safe preflight or deployment check failed."""


class ApplyError(RuntimeError):
    """A failure occurred after the live-file apply phase began."""


@dataclasses.dataclass(frozen=True)
class GitFile:
    mode: str
    oid: str
    content: bytes


@dataclasses.dataclass
class Operation:
    path: str
    action: str  # write, delete, or already
    target: Optional[GitFile]
    existing_stat: Optional[os.stat_result]


def run_git(repo: pathlib.Path, *args: str, check: bool = True) -> bytes:
    command = [
        "git",
        "-c",
        "safe.directory=" + str(repo),
        "-C",
        str(repo),
        *args,
    ]
    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if check and result.returncode != 0:
        detail = result.stderr.decode("utf-8", "replace").strip()
        raise DeployError("Git command failed ({}): {}".format(" ".join(args), detail))
    return result.stdout


def normalized(data: bytes) -> bytes:
    """Treat CRLF/LF as equivalent for text, preserving binary content."""
    if b"\x00" not in data:
        return data.replace(b"\r\n", b"\n")
    return data


def safe_relative_path(relative: str) -> Tuple[str, ...]:
    if not relative or relative.startswith("/") or "\\" in relative:
        raise DeployError("Unsafe changed path: {!r}".format(relative))
    parts = tuple(relative.split("/"))
    if any(part in ("", ".", "..") for part in parts):
        raise DeployError("Unsafe changed path: {!r}".format(relative))
    if any(part in PROTECTED_SEGMENTS for part in parts):
        raise DeployError("Protected path changed; not deploying: {}".format(relative))
    if any(part.lower().startswith(".env") for part in parts):
        raise DeployError("Environment file changed; not deploying: {}".format(relative))
    if any(part.lower().startswith(("uploads.copied_", "uploads_backup", "uploads-backup")) for part in parts):
        raise DeployError("Upload backup path changed; not deploying: {}".format(relative))
    if relative in REPO_ONLY_PATHS:
        return parts
    if relative.lower().endswith(".sql") and not relative.startswith("database/migrations/"):
        raise DeployError("Non-migration SQL file changed; review manually: {}".format(relative))
    return parts


def tree_file(repo: pathlib.Path, revision: str, relative: str) -> Optional[GitFile]:
    listing = run_git(repo, "ls-tree", "-z", "-r", revision, "--", relative)
    found = None
    for record in listing.split(b"\0"):
        if not record:
            continue
        try:
            header, raw_path = record.split(b"\t", 1)
            mode, kind, oid = header.decode("ascii").split(" ")
        except (ValueError, UnicodeDecodeError) as exc:
            raise DeployError("Could not parse Git tree entry for {}".format(relative)) from exc
        if os.fsdecode(raw_path) != relative:
            continue
        if kind != "blob" or mode not in ("100644", "100755"):
            raise DeployError("Only regular files can be deployed: {}".format(relative))
        content = run_git(repo, "cat-file", "blob", oid)
        found = GitFile(mode=mode, oid=oid, content=content)
        break
    return found


def is_repo_only(relative: str) -> bool:
    return relative in REPO_ONLY_PATHS or any(relative.startswith(prefix) for prefix in REPO_ONLY_PREFIXES)


def checkout_status(repo: pathlib.Path) -> Tuple[List[str], List[str]]:
    tracked_live_files = run_git(repo, "ls-files", "-z", "--", "release_blue_candidate_20260930")
    if tracked_live_files:
        raise DeployError("The live release folder is tracked inside the Git checkout; refusing deployment.")
    # The live release is nested under the checkout but intentionally not
    # versioned there. Keep tracked edits fail-closed, but tolerate unrelated
    # untracked NAS data after checking that it cannot collide with this update.
    raw_status = run_git(
        repo,
        "status",
        "--porcelain",
        "-z",
        "--untracked-files=all",
        "--",
        ".",
        ":(exclude)release_blue_candidate_20260930",
    )
    tracked_changes: List[str] = []
    untracked_paths: List[str] = []
    for record in raw_status.split(b"\0"):
        if not record:
            continue
        status_code = record[:2]
        path = os.fsdecode(record[3:])
        if status_code == b"??":
            untracked_paths.append(path)
        else:
            tracked_changes.append(path)
    return tracked_changes, untracked_paths


def untracked_path_conflicts(untracked_paths: Sequence[str], incoming_paths: Sequence[str]) -> List[str]:
    """Find untracked files that would block an incoming file or directory."""
    untracked = set(untracked_paths)
    incoming = set(incoming_paths)
    conflicts = set()

    for path in incoming:
        if path in untracked:
            conflicts.add(path)
        parts = path.split("/")
        for depth in range(1, len(parts)):
            parent = "/".join(parts[:depth])
            if parent in untracked:
                conflicts.add(parent)

    for path in untracked:
        parts = path.split("/")
        for depth in range(1, len(parts)):
            parent = "/".join(parts[:depth])
            if parent in incoming:
                conflicts.add(path)

    return sorted(conflicts)


def has_live_entrypoint(live: pathlib.Path) -> bool:
    """Accept either supported Web Station document-root layout."""
    candidates = (live / "public" / "index.php", live / "index.php")
    return any(path.is_file() and not path.is_symlink() for path in candidates)


def target_path(live: pathlib.Path, relative: str) -> pathlib.Path:
    parts = safe_relative_path(relative)
    candidate = live.joinpath(*parts)
    current = live
    for part in parts[:-1]:
        current = current / part
        try:
            info = current.lstat()
        except FileNotFoundError:
            continue
        if stat.S_ISLNK(info.st_mode):
            raise DeployError("Symlink in live path; refusing: {}".format(current))
        if not stat.S_ISDIR(info.st_mode):
            raise DeployError("Non-directory in live path; refusing: {}".format(current))
    return candidate


def classify(live_data: Optional[bytes], old: Optional[GitFile], new: Optional[GitFile]) -> str:
    """Return the safe action for one path, or raise on an unknown live edit."""
    if new is None:
        if live_data is None:
            return "already"
        if old is not None and normalized(live_data) == normalized(old.content):
            return "delete"
        raise DeployError("Live file differs from the old version; refusing deletion")

    if live_data is None:
        if old is None:
            return "write"
        raise DeployError("Expected existing live file is missing")

    if normalized(live_data) == normalized(new.content):
        return "already"
    if old is not None and normalized(live_data) == normalized(old.content):
        return "write"
    raise DeployError("Live file differs from both old and target versions")


def plan_operations(repo: pathlib.Path, live: pathlib.Path, base: str, target: str) -> List[Operation]:
    raw_paths = run_git(repo, "diff", "--no-renames", "--name-only", "-z", base, target)
    relatives = [os.fsdecode(value) for value in raw_paths.split(b"\0") if value]
    operations: List[Operation] = []

    for relative in relatives:
        safe_relative_path(relative)
        if is_repo_only(relative):
            operations.append(Operation(relative, "already", None, None))
            continue

        old = tree_file(repo, base, relative)
        new = tree_file(repo, target, relative)
        path = target_path(live, relative)
        try:
            info = path.lstat()
        except FileNotFoundError:
            info = None

        live_data: Optional[bytes]
        if info is None:
            live_data = None
        elif stat.S_ISREG(info.st_mode):
            live_data = path.read_bytes()
        else:
            raise DeployError("Live target is not a regular file; refusing: {}".format(relative))

        try:
            action = classify(live_data, old, new)
        except DeployError as exc:
            raise DeployError("{}: {}".format(relative, exc)) from exc
        if action == "already" and new is not None and info is not None and os.name == "posix":
            desired_mode = int(new.mode, 8) & 0o777
            if stat.S_IMODE(info.st_mode) != desired_mode:
                action = "write"
        operations.append(Operation(relative, action, new, info))

    return operations


def create_backup(backups: pathlib.Path, live: pathlib.Path, operations: Sequence[Operation], base: str, target: str) -> Optional[pathlib.Path]:
    affected = [operation for operation in operations if operation.action in ("write", "delete") and operation.existing_stat is not None]
    deployable = [operation for operation in operations if operation.action in ("write", "delete")]
    if not deployable:
        return None

    backups.mkdir(parents=True, exist_ok=True)
    stamp = _datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
    backup = backups / "iscan-live-before-{}-{}.tar.gz".format(stamp, target[:12])
    manifest = [
        "base={}".format(base),
        "target={}".format(target),
        "files:",
    ] + [operation.path for operation in deployable]

    previous_umask = os.umask(0o077)
    try:
        with tarfile.open(str(backup), mode="w:gz") as archive:
            for operation in affected:
                archive.add(str(live / pathlib.PurePosixPath(operation.path)), arcname=operation.path, recursive=False)
            payload = ("\n".join(manifest) + "\n").encode("utf-8")
            info = tarfile.TarInfo("DEPLOYMENT_MANIFEST.txt")
            info.size = len(payload)
            info.mode = 0o600
            archive.addfile(info, fileobj=io.BytesIO(payload))
        os.chmod(str(backup), 0o600)
    except Exception:
        try:
            backup.unlink()
        except OSError:
            pass
        raise
    finally:
        os.umask(previous_umask)
    return backup


def write_file(path: pathlib.Path, operation: Operation) -> None:
    assert operation.target is not None
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
    fd, temporary_name = tempfile.mkstemp(prefix=".iscan-deploy-", dir=str(path.parent))
    temporary = pathlib.Path(temporary_name)
    try:
        with os.fdopen(fd, "wb") as handle:
            handle.write(operation.target.content)
            handle.flush()
            os.fsync(handle.fileno())
        os.chmod(str(temporary), int(operation.target.mode, 8) & 0o777)
        if operation.existing_stat is not None and hasattr(os, "chown"):
            os.chown(str(temporary), operation.existing_stat.st_uid, operation.existing_stat.st_gid)
        os.replace(str(temporary), str(path))
    finally:
        try:
            temporary.unlink()
        except FileNotFoundError:
            pass


def run_deployment(repo: pathlib.Path, live: pathlib.Path, backups: pathlib.Path) -> int:
    repo = repo.absolute()
    live = live.absolute()
    backups = backups.absolute()

    if hasattr(os, "geteuid") and os.geteuid() != 0:
        raise DeployError("Run this deployment task as root so it can preserve live file ownership.")
    if not (repo / ".git").is_dir():
        raise DeployError("Git checkout not found: {}".format(repo))
    if live.is_symlink() or not live.is_dir():
        raise DeployError("Live release folder is missing or is a symlink: {}".format(live))
    if not (live / ".env").is_file() or not has_live_entrypoint(live):
        raise DeployError(
            "Live release preflight failed (.env or both supported index.php entrypoints are missing)."
        )

    branch = run_git(repo, "branch", "--show-current").decode().strip()
    if branch != "main":
        raise DeployError("Expected NAS Git branch main; found {!r}.".format(branch))

    tracked_changes, untracked_paths = checkout_status(repo)
    if tracked_changes:
        raise DeployError(
            "NAS Git checkout has {} tracked local change(s); nothing deployed.".format(len(tracked_changes))
        )

    base = run_git(repo, "rev-parse", "HEAD").decode().strip()
    run_git(repo, "fetch", "origin", "main")
    target = run_git(repo, "rev-parse", REMOTE_REF).decode().strip()
    if base == target:
        print("IScan_DEPLOY_UP_TO_DATE commit={}".format(target[:12]))
        return 0

    ancestor = subprocess.run(
        ["git", "-c", "safe.directory=" + str(repo), "-C", str(repo), "merge-base", "--is-ancestor", base, target],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if ancestor.returncode != 0:
        raise DeployError("origin/main is not a fast-forward from the NAS checkout; nothing deployed.")

    incoming_paths = [
        os.fsdecode(value)
        for value in run_git(repo, "diff", "--no-renames", "--name-only", "-z", base, target).split(b"\0")
        if value
    ]
    collisions = untracked_path_conflicts(untracked_paths, incoming_paths)
    if collisions:
        raise DeployError(
            "Untracked NAS file(s) conflict with incoming Git paths; nothing deployed: {}".format(
                ", ".join(collisions[:5])
            )
        )

    operations = plan_operations(repo, live, base, target)
    backup = create_backup(backups, live, operations, base, target)
    if backup is not None:
        print("BACKUP={}".format(backup))

    migrations = [
        operation.path
        for operation in operations
        if operation.path.startswith("database/migrations/") and operation.path.lower().endswith(".sql")
    ]
    try:
        for operation in operations:
            if is_repo_only(operation.path) or operation.action == "already":
                continue
            path = target_path(live, operation.path)
            if operation.action == "delete":
                path.unlink()
                print("DELETED {}".format(operation.path))
            elif operation.action == "write":
                assert operation.target is not None
                write_file(path, operation)
                print("UPDATED {}".format(operation.path))
        # Verify all target files before advancing the checkout's deployment base.
        for operation in operations:
            if is_repo_only(operation.path):
                continue
            path = target_path(live, operation.path)
            if operation.target is None:
                if path.exists():
                    raise DeployError("Post-deploy verification failed; expected deleted file: {}".format(operation.path))
            elif (
                not path.is_file()
                or normalized(path.read_bytes()) != normalized(operation.target.content)
                or (
                    os.name == "posix"
                    and stat.S_IMODE(path.stat().st_mode) != (int(operation.target.mode, 8) & 0o777)
                )
            ):
                raise DeployError("Post-deploy verification failed: {}".format(operation.path))

        run_git(repo, "merge", "--ff-only", REMOTE_REF)
    except Exception as exc:
        backup_note = " Backup: {}.".format(backup) if backup is not None else ""
        raise ApplyError(
            "Failure after deployment began; inspect live files before retrying.{} Cause: {}".format(backup_note, exc)
        ) from exc
    print("SOURCE_CHECKOUT_UPDATED={}".format(target[:12]))
    if migrations:
        print("DATABASE_MIGRATIONS_REVIEW_REQUIRED=" + ",".join(migrations))
    else:
        print("DATABASE_MIGRATIONS=NONE")
    print("ISCAN_NAS_DEPLOY_OK commit={}".format(target[:12]))
    return 0


def main() -> int:
    repo = pathlib.Path(os.environ.get("ISCAN_REPO", DEFAULT_REPO))
    live = pathlib.Path(os.environ.get("ISCAN_LIVE", DEFAULT_LIVE))
    backups = pathlib.Path(os.environ.get("ISCAN_BACKUPS", DEFAULT_BACKUPS))
    try:
        return run_deployment(repo, live, backups)
    except ApplyError as exc:
        print("DEPLOYMENT_NEEDS_REVIEW: {}".format(exc), file=sys.stderr)
        return 2
    except DeployError as exc:
        print("STOP: {}".format(exc), file=sys.stderr)
        return 1
    except Exception as exc:  # Fail closed and retain traceback in the task log.
        print("DEPLOYMENT_ERROR: {}: {}".format(type(exc).__name__, exc), file=sys.stderr)
        raise


if __name__ == "__main__":
    sys.exit(main())
