// Lanceur du serveur MCP de LatePoint (say-book.com) pour Claude.
// Lit l'identifiant et le mot de passe d'application dans C:\Users\rogue\Claude\wp-sites.json
// (entrée "saybook") au lieu de les recopier en clair dans la configuration de Claude,
// puis démarre le pont officiel @automattic/mcp-wordpress-remote (version figée).
import { readFileSync } from 'node:fs';
import { spawn } from 'node:child_process';

const PONT = '@automattic/mcp-wordpress-remote@0.5.4';
const site = JSON.parse(readFileSync('C:/Users/rogue/Claude/wp-sites.json', 'utf8')).saybook;

const enfant = spawn('npx', ['-y', PONT], {
	stdio: 'inherit',
	shell: process.platform === 'win32',
	env: {
		...process.env,
		WP_API_URL: site.URL.replace(/\/$/, '') + '/wp-json/latepoint/v1/mcp',
		WP_API_USERNAME: site.USER,
		WP_API_PASSWORD: site.PASS,
	},
});
enfant.on('exit', (code) => process.exit(code ?? 0));
