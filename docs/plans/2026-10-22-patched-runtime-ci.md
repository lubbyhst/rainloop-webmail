# Patched Runtime and CI Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build this fork into a tested PHP-security-patched image that can later be referenced by the Flux homelab.

**Architecture:** Build web assets from the checked-out fork in a pinned Node builder. Package them with patched PHP 8.3 Apache, run as UID 33 on port 8080, and keep the existing /rainloop/data layout. Publish the same tested image to GHCR only from master or a matching version tag; create GitHub releases for version tags.

**Tech Stack:** PHP 8.3.36, Apache, Node 24, Yarn Classic, Gulp/Webpack, Python unittest, Docker, GitHub Actions/GHCR.

## Constraints and design decisions

- The PHP CVEs are runtime bugs; changing RainLoop PHP application code cannot repair them.
- Never restart, replace, update or mount production RainLoop or its data for these checks.
- Use a new feature branch in the user-requested dedicated clone.
- Preserve the existing application build rather than downloading an unrelated release archive.
- Prefer a patched runtime rebuild over a PHP-core backport maintained in this app repository. A full webmail migration remains separate work.
- Scope the first image to linux/amd64, matching the homelab; do not claim untested architectures.
- Pull requests get read-only checks. Publishing has a separate job and the minimum write permissions.
- Pin third-party actions and base images. Enable Dependabot for updates.
- Do not automatically deploy to the homelab or publish a release tag during implementation.

## Task 1: Regression and compatibility checks

- [x] Add tests/runtime.php covering actual PHP version, required extensions and production settings.
- [x] Run against the previous immutable image and record the expected version/settings failures.
- [x] Add PHP mail-address/MIME compatibility tests and an isolated Docker HTTP smoke suite.

## Task 2: Patched image and source build

- [x] Add a source-built multistage Dockerfile, production Apache/PHP config and health endpoint.
- [x] Use /rainloop/data as the only persistent application directory, deny HTTP access to private files.
- [x] Update the development PHP runtime too, so docker-compose does not retain PHP 8.0.
- [x] Add a container build task and update Webpack only as needed for supported Node/OpenSSL.
- [x] Build locally or on an isolated Docker host, run syntax/unit/runtime/HTTP checks.

## Task 3: CI and releasing

- [x] Replace the obsolete workflow with source lint, workflow/Dockerfile checks, image build and tests.
- [x] Transfer the tested image to a separate publishing job, preserving its image ID.
- [x] Implement SHA/edge publication on master and version/latest publication on a matching vX.Y.Z tag; do not publish during implementation.
- [x] Generate an SBOM in CI and implement provenance attestation/release digest references for publication.
- [x] Document release commands, GHCR visibility and later homelab port/volume/digest requirements.

## Task 4: Verification and review

- [x] Run the same checks used by CI and inspect the diff for defects.
- [x] Commit and push the feature branch, open a draft PR, and inspect hosted CI results.
- [x] Report exact passing checks, remaining limits and that production is untouched.

## Verification evidence

Code commit `f06e75b43a80f40638678e375a33ce117796e96f` passed the full hosted PR pipeline:
https://github.com/lubbyhst/rainloop-webmail/actions/runs/39855524452

All eight container smoke tests, shipped-PHP syntax, JavaScript lint, workflow/Dockerfile lint and SBOM generation passed. The previous image failed the runtime regression check and the previous Float implementation failed the vCard/calendar regression check before the fixes. Independent review findings in the SMTP fixture were corrected.

The code is in PR #1; no master merge, release tag, GHCR publication or homelab rollout was performed. Real Netcup/TLS/Ajax-session integration and migration of existing data remain separate checks, as documented in the container guide.
