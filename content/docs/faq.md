---
title: "FAQ"
weight: 10
---

## General

{{< details title="What is OpenCloudTouch?" >}}
OpenCloudTouch is an open-source replacement for the Bose SoundTouch cloud services. It runs locally on your network and restores functionality that was lost when Bose discontinued their cloud infrastructure.
{{< /details >}}

{{< details title="Which speakers are supported?" >}}
All Bose SoundTouch speakers are supported:

| Model | Status |
|-------|--------|
| SoundTouch 10 | ✅ Verified |
| SoundTouch 20 | ✅ Verified |
| SoundTouch 30 | ✅ Verified |
| SoundTouch 300 (Soundbar) | ✅ Verified |
| Wave SoundTouch Music System IV | ✅ Verified |
| SoundTouch SA-5 Amplifier | ⚠️ Experimental — see [community discussion](https://github.com/opencloudtouch/opencloudtouch/discussions/67) |
| Lifestyle 535/600/650 | 🔬 Untested — see [discussion #214](https://github.com/opencloudtouch/opencloudtouch/discussions/214) for community progress |

If it has "SoundTouch" in the name, it should work.
{{< /details >}}

{{< details title="Do I need to modify my speakers?" >}}
No. OpenCloudTouch works with unmodified speakers. No firmware changes, no soldering, no hardware mods. Your warranty (if any remains) stays intact.
{{< /details >}}

{{< details title="Does it need internet access?" >}}
OpenCloudTouch itself works fully offline. However, streaming internet radio stations obviously requires an internet connection for the audio stream.
{{< /details >}}

## Setup

{{< details title="What hardware do I need?" >}}
A Raspberry Pi 4 or 5 is the recommended setup. We also provide **ready-to-flash Raspberry Pi images** with everything pre-configured — just write the image to an SD card, boot, and go.

Any Linux machine, NAS, or Docker-capable host on your local network works fine too. Minimum requirements: 512 MB RAM, 1 CPU core, 100 MB disk space.
{{< /details >}}

{{< details title="Can I run it on a NAS (Synology, QNAP)?" >}}
Yes! If your NAS supports Docker containers, you can run OpenCloudTouch. Use `--network host` for automatic speaker discovery.
{{< /details >}}

{{< details title="Can multiple instances run on the same network?" >}}
You should only run one instance per network. Multiple instances will compete for speaker control and cause conflicts.
{{< /details >}}

## Troubleshooting

{{< details title="My speakers are not showing up" >}}

1. Make sure OpenCloudTouch and your speakers are on the **same subnet**
2. Check that multicast traffic (UDP 1900) is not blocked by your router/firewall
3. If using Docker Desktop on macOS/Windows, multicast doesn't work in the VM — see [Network Configuration]({{< ref "/docs/network-config" >}})
4. Try restarting your speakers (unplug for 10 seconds)
5. If automatic discovery doesn't work, you can add speaker IPs manually in the OpenCloudTouch web interface

{{< /details >}}

{{< details title="Presets are not saving" >}}
Check that the data volume is mounted correctly in Docker. Without persistent storage, presets are lost on container restart:

```bash
docker run -v oct-data:/data ...
```

{{< /details >}}

## Contributing

{{< details title="How can I help?" >}}

- [Report a bug](https://github.com/opencloudtouch/opencloudtouch/issues/new?template=bug_report.yml)
- [Request a feature](https://github.com/opencloudtouch/opencloudtouch/issues/new?template=feature_request.yml)
- Discuss ideas in [GitHub Discussions](https://github.com/opencloudtouch/opencloudtouch/discussions)
- Submit pull requests — check the contributing guide in the repository
- Test with different speaker models and report compatibility

{{< /details >}}

{{< details title="I found a security issue" >}}
Please report security vulnerabilities privately via [GitHub Security Advisories](https://github.com/opencloudtouch/opencloudtouch/security/advisories/new) or email [security@opencloudtouch.org](mailto:security@opencloudtouch.org). Do **not** create public issues for security vulnerabilities.
{{< /details >}}
