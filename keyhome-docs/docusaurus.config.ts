import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'KeyHome Docs',
  tagline: 'Frontend technical documentation',
  favicon: 'img/logo.png',
  future: { v4: true },
  url: 'http://localhost',
  baseUrl: '/',
  organizationName: 'keyhome',
  projectName: 'keyhome-frontend-next',
  onBrokenLinks: 'warn',
  i18n: { defaultLocale: 'en', locales: ['en'] },
  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: '/docs',
        },
        blog: false,
        theme: { customCss: './src/css/custom.css' },
      } satisfies Preset.Options,
    ],
  ],
  themeConfig: {
    image: 'img/logo.png',
    navbar: {
      logo: {
        alt: 'KeyHome Logo',
        src: 'img/logo.png',
      },
      title: 'KeyHome Docs',
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'tutorialSidebar',
          position: 'left',
          label: 'Documentation',
        },
      ],
      style: 'primary',
    },
    footer: {
      style: 'dark',
      copyright: `Copyright © ${new Date().getFullYear()} KeyHome. All rights reserved.`,
    },
    colorMode: { defaultMode: 'light', respectPrefersColorScheme: true },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['typescript', 'bash', 'json', 'php'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
