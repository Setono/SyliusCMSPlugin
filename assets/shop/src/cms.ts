/**
 * The shop script of setono/sylius-cms-plugin, built to src/Resources/public/shop/cms.js by "npm run build" and loaded on
 * every shop page by the setono_sylius_cms_javascript block. It has no dependencies. See "Loading the stylesheet and
 * script" in docs/spec/blocks.md
 */
import { start } from './start';

// Read while the script runs: currentScript is null once it has
start(document, document.currentScript);
