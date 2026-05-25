---
title: "Security"
weight: 90
bookToc: false
---

# Security Policy

## Reporting Vulnerabilities

If you discover a security vulnerability in OpenCloudTouch, please report it responsibly:

1. **Preferred:** [GitHub Private Vulnerability Reporting](https://github.com/opencloudtouch/opencloudtouch/security/advisories/new)
2. **Email:** [security@opencloudtouch.org](mailto:security@opencloudtouch.org)

{{< hint danger >}}
**Do NOT create public GitHub issues for security vulnerabilities.** Use the private channels above.
{{< /hint >}}

## What Happens Next

- Your report will be acknowledged within **48 hours**
- A fix will be developed privately
- A security advisory will be published after the fix is released
- You will be credited (unless you prefer otherwise)

## Scope

OpenCloudTouch runs on your local network. The primary attack surface is:

- REST API (port 8090)
- WebSocket endpoint (port 8091)
- SSDP/mDNS multicast listeners

We take all reports seriously, even for local-network-only services.
