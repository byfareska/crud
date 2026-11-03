# Security Policy

## Supported versions

Only the latest release receives security fixes. While the bundle is in `0.x`, fixes land in the next minor or patch
release; there are no backports.

## Reporting a vulnerability

Please do not open a public issue. Report it privately through GitHub:
[Report a vulnerability](https://github.com/byfareska/crud/security/advisories/new).

Include what is affected (the version, the driver, the configuration), how to reproduce it and what an attacker gains.
You will get an answer within a few days. Once a fix is released, the advisory is published with credit to the
reporter, unless you prefer otherwise.

Who may see a resource, a record or a panel is decided by the bundle (see "Who may do what with a resource" in the
README), so a way past a `role`, a guard or a scope is a vulnerability. What the application's own code allows - its
voters, its guards, the parameters of its addresses - is the application's.
