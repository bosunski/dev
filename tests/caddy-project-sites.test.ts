import { describe, expect, test } from 'bun:test'
import { existsSync, mkdtempSync, readdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { CaddyStep } from '../src/plugins/caddy/steps/caddy-step.js'
import { caddyProjectSitesDir, caddySiteFilename } from '../src/plugins/caddy/caddy-layout.js'
import { Runner } from '../src/execution/runner.js'
import { Config } from '../src/config/config.js'
import { Repository } from '../src/execution/repository.js'
import type { IOInterface } from '../src/types/io.js'

const silentIO: IOInterface = {
  writeln: () => {}, write: () => {}, info: () => {}, error: () => {}, dev: () => {},
  stepStart: () => {}, stepEnd: () => {},
  text: async () => '', password: async () => '',
}

function runnerFor(cwd: string): Runner {
  return new Runner(new Config(cwd, {}), silentIO, new Repository())
}

describe('project-scoped Caddy sites', () => {
  test('uses separate stable directories for separate projects', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-caddy-projects-'))
    expect(caddyProjectSitesDir(root, '/work/alpha'))
      .not.toBe(caddyProjectSitesDir(root, '/other/alpha'))
  })

  test('removes only stale configs owned by the current project', async () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-caddy-projects-'))
    const alphaDir = caddyProjectSitesDir(root, '/work/alpha')
    const betaDir = caddyProjectSitesDir(root, '/work/beta')

    await new CaddyStep({ sites: ['old.alpha.test', 'keep.alpha.test'] }, alphaDir)
      .run(runnerFor('/work/alpha'))
    await new CaddyStep({ sites: ['beta.test'] }, betaDir)
      .run(runnerFor('/work/beta'))
    await new CaddyStep({ sites: ['keep.alpha.test'] }, alphaDir)
      .run(runnerFor('/work/alpha'))

    expect(existsSync(join(alphaDir, caddySiteFilename('old.alpha.test')))).toBeFalse()
    expect(readdirSync(alphaDir)).toEqual([caddySiteFilename('keep.alpha.test')])
    expect(readdirSync(betaDir)).toEqual([caddySiteFilename('beta.test')])
  })

  test('deploys only after all site configuration has been written', async () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-caddy-deploy-order-'))
    const sitesDir = caddyProjectSitesDir(root, '/work/alpha')
    const siteFile = join(sitesDir, caddySiteFilename('alpha.test'))
    let deployed = false

    const step = new CaddyStep({ sites: ['alpha.test'] }, sitesDir, async () => {
      expect(existsSync(siteFile)).toBeTrue()
      deployed = true
      return true
    })

    expect(await step.run(runnerFor('/work/alpha'))).toBeTrue()
    expect(deployed).toBeTrue()
  })
})
