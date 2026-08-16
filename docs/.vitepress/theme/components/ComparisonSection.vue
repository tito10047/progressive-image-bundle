<script setup lang="ts">
const rows = [
  { feature: 'Standalone URL function (Twig)', pgi: 'pgi_filter()', liip: 'imagine_filter()' },
  { feature: 'On-the-fly HTTP resolve route', pgi: true, liip: true },
  { feature: 'CLI cache warm / remove', pgi: true, liip: true },
  { feature: 'Responsive <picture>/srcset out of the box', pgi: true, liip: false },
  { feature: 'Blurhash placeholder + zero CLS', pgi: true, liip: false },
  { feature: 'AVIF/WebP content negotiation', pgi: true, liip: false },
  { feature: 'Real post-processing (jpegoptim/pngquant/cwebp/avifenc)', pgi: true, liip: false },
  { feature: 'Content-addressed, immutable cache (no cache-busting)', pgi: true, liip: false },
  { feature: 'Three generation strategies (sync / terminate / async)', pgi: true, liip: false },
  { feature: 'DDD core with contract tests per extension point', pgi: true, liip: false },
];
</script>

<template>
  <section class="comparison">
    <div class="comparison-inner">
      <h2 class="comparison-title">Coming from LiipImagineBundle?</h2>
      <p class="comparison-subtitle">
        Every workflow you rely on today has a PGI equivalent — plus the responsive,
        zero-CLS pipeline Liip never had.
      </p>

      <table class="comparison-table">
        <thead>
          <tr>
            <th class="feature-col">Feature</th>
            <th class="pgi-col">Progressive Image Bundle</th>
            <th class="liip-col">LiipImagineBundle</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.feature">
            <td class="feature-col">{{ row.feature }}</td>
            <td class="pgi-col">
              <span v-if="row.pgi === true" class="mark yes">✓</span>
              <span v-else class="mark code">{{ row.pgi }}</span>
            </td>
            <td class="liip-col">
              <span v-if="row.liip === true" class="mark yes muted">✓</span>
              <span v-else-if="row.liip === false" class="mark no">—</span>
              <span v-else class="mark code muted">{{ row.liip }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <p class="comparison-footnote">
        Full breakdown and a filter-set migration table in the
        <a href="/guide/getting-started">Getting Started guide</a>.
      </p>
    </div>
  </section>
</template>

<style scoped>
.comparison {
  padding: 64px 24px 8px;
  border-top: 1px solid var(--vp-c-divider);
}

.comparison-inner {
  max-width: 840px;
  margin: 0 auto;
}

.comparison-title {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 8px;
}

.comparison-subtitle {
  text-align: center;
  font-size: 15px;
  color: var(--vp-c-text-2);
  margin: 0 0 32px;
}

.comparison-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 10px;
  overflow: hidden;
}

.comparison-table th,
.comparison-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--vp-c-divider);
  text-align: center;
}

.comparison-table .feature-col {
  text-align: left;
  color: var(--vp-c-text-1);
}

.comparison-table thead th {
  background: var(--vp-c-bg-soft);
  font-weight: 600;
}

.comparison-table thead .pgi-col {
  color: var(--vp-c-brand-1);
}

.comparison-table tbody tr:last-child td {
  border-bottom: none;
}

.mark.yes {
  color: var(--vp-c-brand-1);
  font-weight: 700;
}

.mark.yes.muted {
  color: var(--vp-c-text-3);
  font-weight: 700;
}

.mark.no {
  color: var(--vp-c-text-3);
}

.mark.code {
  font-family: var(--vp-font-family-mono);
  font-size: 12.5px;
  color: var(--vp-c-text-2);
}

.comparison-footnote {
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
  color: var(--vp-c-text-3);
}
</style>
