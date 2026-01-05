# <img src="logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Progressive Image Bundle Documentation

Welcome to the documentation for the **Progressive Image Bundle** for Symfony. This bundle helps you deliver lightning-fast and fully responsive images with minimal
effort.

## 📌 Contents

1. [**Installation**](installation.md) - How to get started with the bundle.
2. [**Configuration**](configuration.md) - Detailed overview of all settings.
3. [**Usage**](usage.md) - How to use the Twig component in templates.
4. [**Responsive Strategy**](responsive-strategy.md) - Grid, breakpoints, and upscaling protection.
5. [**Advanced Features**](advanced.md) - LCP optimization, HTML caching, and architecture.

---

## 🚀 Why Progressive Image Bundle?

- **Fully Responsive:** Automatic generation of `srcset` and `sizes` based on your grid (Tailwind, Bootstrap, or custom).
- **Modern Placeholders:** Integrated support for Blurhash.
- **High Performance:** Stream-based metadata reading without loading entire images into memory.
- **Zero CLS:** Automatically reserves space for the image in the layout.
- **Smart Preload:** Improve LCP score by automatically preloading critical images.
- **LiipImagine Integration:** Seamless cooperation with the most popular Symfony image processing bundle.
