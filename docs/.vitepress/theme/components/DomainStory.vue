<template>
  <section class="domain-story">
    <div class="domain-story-inner">
      <p class="domain-story-domain">
        <a href="https://pgi.blue" class="pgi-domain">pgi.blue</a>
      </p>
      <p class="domain-story-text">
        <strong>P</strong>ro<strong>g</strong>ressive <strong>I</strong>mage, on the blue
        channel. The bundle spends its time pushing bytes through the R, G and B planes of
        every image it touches &mdash; blue felt like the right home for it: clean, a
        little technical, no punchline required.
      </p>
      <p class="domain-story-proof">
        Running in production on
        <a href="https://mostka.sk" target="_blank" rel="noopener">mostka.sk</a>
        and
        <a href="https://vsetkosada.sk" target="_blank" rel="noopener">vsetkosada.sk</a>.
      </p>
    </div>
  </section>
</template>

<style scoped>
.domain-story {
  padding: 56px 24px 64px;
  border-top: 1px solid var(--vp-c-divider);
}

.domain-story-inner {
  max-width: 620px;
  margin: 0 auto;
  text-align: center;
}

.domain-story-domain {
  margin: 0 0 12px;
  font-size: 22px;
}

.domain-story-domain a {
  text-decoration: none;
}

.domain-story-text {
  font-size: 14.5px;
  line-height: 1.7;
  color: var(--vp-c-text-3);
  margin: 0;
}

.domain-story-text strong {
  color: var(--vp-c-text-2);
}

.domain-story-proof {
  margin: 16px 0 0;
  font-size: 13px;
  color: var(--vp-c-text-3);
}

.domain-story-proof a {
  color: var(--vp-c-brand-1);
  text-decoration: none;
}

.domain-story-proof a:hover {
  text-decoration: underline;
}
</style>
