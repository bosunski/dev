import { existsSync } from 'node:fs'
import { join } from 'node:path'

export function valetBinCandidates(): string[] {
  const home = process.env['HOME'] ?? ''
  const composerHome = process.env['COMPOSER_HOME'] ?? composerGlobalHome()
  const candidates = [
    composerHome ? join(composerHome, 'vendor/bin/valet') : '',
    join(home, '.composer/vendor/bin/valet'),
    join(home, '.config/composer/vendor/bin/valet'),
  ]

  return [...new Set(candidates.filter(Boolean))]
}

export function installedValetBin(): string | null {
  return valetBinCandidates().find(candidate => existsSync(candidate)) ?? null
}

export function composerValetBin(): string | null {
  return valetBinCandidates()[0] ?? null
}

function composerGlobalHome(): string {
  const proc = Bun.spawnSync(['composer', 'global', 'config', 'home', '--no-interaction'], {
    stdout: 'pipe',
    stderr: 'pipe',
  })
  if (proc.exitCode !== 0) return ''

  const lines = new TextDecoder().decode(proc.stdout).trim().split('\n').filter(line => line.trim())
  return lines[lines.length - 1]?.trim() ?? ''
}
