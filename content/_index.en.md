---
title: OpenCloudTouch
layout: hextra-home
---

{{< hextra/hero-badge link="https://github.com/opencloudtouch/opencloudtouch" >}}
  <div class="hx:w-2 hx:h-2 hx:rounded-full hx:bg-primary-400"></div>
  <span>Open Source & Free</span>
  {{< icon name="arrow-circle-right" attributes="height=14" >}}
{{< /hextra/hero-badge >}}

<div class="hx:mt-6 hx:mb-6">
{{< hextra/hero-headline >}}
  Free your Bose SoundTouch&nbsp;<br class="hx:sm:block hx:hidden" />speakers from the cloud
{{< /hextra/hero-headline >}}
</div>

<div class="hx:mb-12">
{{< hextra/hero-subtitle >}}
  Bose shut down SoundTouch cloud services — your speakers lost presets,&nbsp;<br class="hx:sm:block hx:hidden" />internet radio, and multi-room control. OpenCloudTouch brings it all back.
{{< /hextra/hero-subtitle >}}
</div>

<div class="hx:mb-6">
{{< hextra/hero-button text="Get Started" link="/docs/getting-started/" >}}
</div>

<div class="hx:mt-6"></div>

{{< hextra/feature-grid >}}
  {{< hextra/feature-card
    title="No Cloud Required"
    icon="cloud"
    subtitle="Everything runs locally on a Raspberry Pi or any Docker host. Your music stays on your network — no Bose servers, no tracking, no dependency on services that can be shut down."
    class="hx:aspect-auto hx:md:aspect-[1.1/1] hx:max-md:min-h-[340px]"
    image="/images/screenshots/hero-speaker-2.png"
    imageClass="hx:top-[40%] hx:left-[24px] hx:w-[180%] hx:sm:w-[110%] hx:dark:opacity-80"
    style="background: radial-gradient(ellipse at 50% 80%,rgba(16,185,129,0.15),hsla(0,0%,100%,0));"
  >}}
  {{< hextra/feature-card
    title="Full Speaker Control"
    icon="music-note"
    subtitle="Presets, internet radio, multi-room grouping, volume control — all the features you had before, powered by open-source software you own."
    class="hx:aspect-auto hx:md:aspect-[1.1/1] hx:max-md:min-h-[340px]"
    image="/images/screenshots/webui-speakers.png"
    imageClass="hx:top-[40%] hx:left-[24px] hx:w-[180%] hx:sm:w-[110%] hx:dark:opacity-80"
    style="background: radial-gradient(ellipse at 50% 80%,rgba(59,130,246,0.15),hsla(0,0%,100%,0));"
  >}}
  {{< hextra/feature-card
    title="Easy Setup"
    icon="lightning-bolt"
    subtitle="Pull a Docker image, point your speakers to it, done. No soldering, no firmware mods, no voided warranties. Works with every SoundTouch speaker ever made."
    class="hx:aspect-auto hx:md:aspect-[1.1/1] hx:max-md:min-h-[340px]"
    image="/images/screenshots/setup-raspi.png"
    imageClass="hx:top-[40%] hx:left-[24px] hx:w-[180%] hx:sm:w-[110%] hx:dark:opacity-80"
    style="background: radial-gradient(ellipse at 50% 80%,rgba(245,158,11,0.15),hsla(0,0%,100%,0));"
  >}}
  {{< hextra/feature-card
    title="Open Source"
    icon="code"
    subtitle="MIT licensed. Fully transparent. Contributions welcome. Built by people who love their speakers and refuse to let them become e-waste."
  >}}
  {{< hextra/feature-card
    title="Active Development"
    icon="trending-up"
    subtitle="Regular releases, responsive maintainers, and a growing community. Check out the GitHub Discussions to get involved."
  >}}
  {{< hextra/feature-card
    title="Privacy First"
    icon="lock-closed"
    subtitle="Zero telemetry. Zero cloud dependencies. Your listening habits are nobody's business. OpenCloudTouch doesn't phone home — ever."
  >}}
{{< /hextra/feature-grid >}}
