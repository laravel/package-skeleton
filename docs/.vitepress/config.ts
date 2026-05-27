import { defineConfig } from 'vitepress'

const configuredBase = process.env.VITEPRESS_BASE ?? '/'
const base = configuredBase.endsWith('/') ? configuredBase : `${configuredBase}/`

export default defineConfig({
  title: ':package_name',
  description: ':package_description',
  base,
  themeConfig: {
    sidebar: [
      { text: 'Overview', link: '/' },
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Configuration', link: '/getting-started/configuration' },
          { text: 'Changelog', link: '/getting-started/changelog' },
        ],
      },
      {
        text: 'The Basics',
        items: [
          { text: 'Usage', link: '/basics/usage' },
        ],
      },
    ],
    search: {
      provider: 'local',
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/:vendor_slug/:package_slug' },
    ],
    editLink: {
      pattern: 'https://github.com/:vendor_slug/:package_slug/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },
  },
})
