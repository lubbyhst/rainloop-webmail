# Container build and release

This unofficial fork packages RainLoop source with **PHP 8.3.36**, which fixes
CVE-2026-109618 and CVE-2026-109619. The PHP interpreter is fixed in the base
image; the application source cannot repair PHP request-initialization bugs.
The bundled Sabre vCard/calendar FLOAT property class is renamed to avoid PHP's
reserved class name, with regression coverage preserving its values.
Upstream RainLoop is archived. This change is not a complete security audit of
RainLoop or its old JavaScript/PHP libraries.

## Build and check

```sh
docker build --pull -t rainloop:test .
python3 tests/container_smoke.py --image rainloop:test
```

The multi-stage build compiles this checkout's JavaScript, CSS and packaged
application tree with Node 24 and the frozen Yarn lockfile. It does not download
an upstream RainLoop zip or reuse the old PHP 8.1 image. Webpack 4.47 supports
modern Node/OpenSSL without enabling the legacy OpenSSL provider. Node/build
dependencies and development files are not copied into the runtime image.

CI checks JavaScript lint, workflow/Dockerfile lint, all shipped PHP syntax,
mail-address/Unicode MIME compatibility, patched PHP and settings through both
CLI and Apache, application/admin pages, built assets, private-file denial and
unprivileged execution with a read-only root filesystem. Loopback-only synthetic
IMAP/SMTP fixtures exercise valid/invalid authentication, message retrieval and
submission through the real MailSo clients. These fixtures do not cover TLS,
RainLoop Ajax session behavior or real provider integration. CI produces an SPDX
SBOM. These tests do not authenticate against a real Netcup mailbox or prove
real mail delivery.

## Run

```sh
docker volume create rainloop-data
docker run --detach --name rainloop --publish 127.0.0.1:8080:8080 \
  --read-only --cap-drop ALL --security-opt no-new-privileges \
  --tmpfs /tmp:rw,nosuid,noexec,size=64m \
  --mount source=rainloop-data,target=/rainloop/data \
  rainloop:test
```

Apache runs as `www-data` (UID/GID **33:33**) and listens on **8080**.
`/rainloop/data` retains the old image's persistent layout. New Docker volumes
inherit the image directory ownership. Existing bind/NFS volumes must already
be writable by UID 33; the image does not recursively change their ownership.
Test an existing-data migration using an isolated backup copy first.

`/healthz` tests Apache/PHP availability without loading mailbox data. It does
not check IMAP/SMTP connectivity. Private data and application internals are
denied by Apache independently of `.htaccess`.

The default admin credentials inherited from RainLoop remain `admin` / `12345`
for a new installation. Set a strong admin password before exposing it, restrict
the admin route, and configure TLS on the trusted ingress/reverse proxy.

## CI and publishing

Pull requests, branch pushes and manual runs perform checks. Only successful
**pushes to master or version tags** may publish. No registry passwords or old
upstream GPG keys are required: the publishing job uses its scoped GITHUB_TOKEN.
Actions and base images are pinned; Dependabot proposes updates weekly.

The build job transfers the exact tested Docker image and its checksum/image ID
to a separate publishing job. It is not rebuilt between testing and publishing.
The publisher adds GitHub provenance for the pushed digest.

| Trigger | GHCR tags | GitHub release |
| --- | --- | --- |
| Push to master | `sha-<full commit SHA>`, `edge` | No |
| Push matching `vX.Y.Z` | `sha-<full commit SHA>`, `X.Y.Z`, `latest` | Yes, with digest reference, SPDX SBOM and checksums |
| Pull request / other branch / manual run | None | No |

The first fork version is **1.17.1**. After merging and verifying master CI:

```sh
git switch master
git pull --ff-only
git tag -a v1.17.1 -m 'RainLoop 1.17.1: patched PHP runtime and container release'
git push origin v1.17.1
```

A release tag must exactly match `package.json`; invalid or mismatching tags
fail before publication. Use a new version/tag for changes; do not move release
tags or silently reuse a version. The workflow can retry an interrupted release.
Confirm Actions are enabled for this fork. After the first publication, configure
the GHCR package visibility as public if unauthenticated homelab pulls are wanted;
otherwise use the approved Kubernetes image-pull secret path.

## Referencing the image from the homelab later

Use the immutable reference in the release's `image-reference.txt`, for example:

```text
ghcr.io/lubbyhst/rainloop-webmail@sha256:<published digest>
```

The homelab Deployment container port and Service targetPort must use **8080**;
the Service's own port can remain 80. Preserve `/rainloop/data`, provide a writable
`/tmp` (for example an emptyDir), use runAsUser/runAsGroup 33 and
allowPrivilegeEscalation false, and drop all capabilities. Validate NFS ownership
using a backup copy before touching live data.

The existing RainLoop pod is being preserved for incident investigation.
Creating this fork/image does not authorize its rollout or reopening external
access. No workflow deploys to the homelab.

## References

- https://www.php.net/archive/2026.php#2026-10-20-1
- https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w
- https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp
