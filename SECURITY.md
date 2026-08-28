# Security Policy

## Supported versions

While this package is pre-1.0, security fixes are applied to the latest release only.

| Version | Supported |
| --- | --- |
| 0.1.x | ✅ |

## Reporting a vulnerability

This package handles clipboard contents, which routinely include passwords, tokens, and private messages — even when it is working correctly. Please treat anything that could expose that data as a security issue rather than a bug.

**Do not open a public issue.** Email **ikromjon98.98@icloud.com** with:

- what the problem is and roughly how severe you judge it,
- steps to reproduce, or a proof of concept,
- the package version, PHP version, and Laravel version,
- whether you are willing to be credited.

You can expect an acknowledgement within a few days. If the report is confirmed, a fix and a release will follow, and you will be credited in the release notes unless you would rather not be.

## Scope

Especially interested in reports about:

- clip content reaching disk, logs, or events after a `PrivacyGuard` should have rejected it,
- ways to read another user's clip history through the package's own APIs,
- content leaking into an event payload that is designed not to carry it — `ClipRejected` in particular reports only a byte count by design.

Out of scope, because these are documented limitations rather than defects:

- **Clip content is stored unencrypted**, protected by ordinary file permissions. Encryption at rest is not provided; bind your own `ClipRepository` if you need it.
- **The nspasteboard concealed-type convention is unenforceable through Electron**, which cannot read custom pasteboard types. `NativePhpClipboardSource` always reports `concealed: false`, so `NotConcealedGuard` passes everything through. Enforcing it requires a native helper. This is called out in the README.
