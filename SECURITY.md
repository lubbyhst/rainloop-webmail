# Security maintenance

This is an unofficial maintenance fork of archived RainLoop. The initial scope
is a source-built container with a patched PHP runtime, protected private paths,
and automated build/release checks. It does not promise that all inherited
application or third-party-library vulnerabilities have been fixed.

Use immutable published digests and keep PHP/base-image updates current. Passing
CI covers the checks documented in docs/container.md; it is not a penetration
test. Report suspected vulnerabilities privately through GitHub's security
advisory feature when enabled, rather than posting credentials or exploit details
in a public issue.
