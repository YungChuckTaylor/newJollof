/**
 * Jollof Living — production asset build.
 *
 * Minifies the client bundle + stylesheet into assets/{js,css}/*.min.*.
 * View::header() automatically serves the .min files when they exist, so
 * after editing assets/js/site.js, assets/js/chat.js or assets/css/site.css
 * you MUST re-run this script (or delete the .min files while developing).
 *
 *   npm install        (first time only)
 *   npm run build
 */
import { build, stop } from 'esbuild';

const jobs = [
  ['assets/js/site.js', 'assets/js/site.min.js', false],
  ['assets/js/chat.js', 'assets/js/chat.min.js', false],
  ['assets/css/site.css', 'assets/css/site.min.css', true],
];

for (const [src, out, isCss] of jobs) {
  await build({
    entryPoints: [src],
    outfile: out,
    minify: true,
    target: 'es2019',
    logLevel: 'info',
    loader: isCss ? { '.css': 'css' } : undefined,
  });
}

await stop();
console.log('\nMinified assets rebuilt — production will serve the .min files.');
