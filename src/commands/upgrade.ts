import { Command, Args, Flags } from '@oclif/core'
import { createWriteStream, unlinkSync, existsSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import pkg from '../../package.json'

const RELEASES_API = 'https://api.github.com/repos/bosunski/dev/releases'

export default class Upgrade extends Command {
  static id = 'upgrade'
  static description = 'Upgrade the application to the latest version or to a specific version'
  static args = {
    version: Args.string({ description: 'Version tag to upgrade to', required: false }),
  }
  static flags = {
    'dry-run': Flags.boolean({ description: 'Perform a dry run without actually upgrading' }),
  }

  async run(): Promise<void> {
    const { args, flags } = await this.parse(Upgrade)
    const dryRun = flags['dry-run']
    const currentVersion = pkg.version

    this.log('Checking for a new version...')

    const url = args.version
      ? `${RELEASES_API}/tags/${args.version}`
      : `${RELEASES_API}/latest`

    const resp = await fetch(url, {
      headers: { 'User-Agent': 'dev-cli', Accept: 'application/vnd.github+json' },
      signal: AbortSignal.timeout(10000),
    })

    if (!resp.ok) {
      this.error(`Failed to fetch release info: ${resp.statusText}`)
    }

    const release = await resp.json() as {
      tag_name?: string
      assets?: Array<{ name: string; browser_download_url: string }>
    }

    const newVersion = release.tag_name?.replace(/^v/, '') ?? null
    if (!newVersion) {
      this.log('No releases available.')
      return
    }

    if (newVersion === currentVersion) {
      this.log('You are already on the latest version.')
      return
    }

    const action = this.compareVersions(currentVersion, newVersion) < 0 ? 'Upgrading' : 'Downgrading'
    this.log(`${action} from ${currentVersion} to ${newVersion}...`)

    if (dryRun) {
      this.log('[dry-run] Would download and replace the current binary.')
      return
    }

    const assetSuffix = process.platform === 'darwin'
      ? (process.arch === 'arm64' ? 'darwin-arm64' : 'darwin-x86_64')
      : (process.arch === 'arm64' ? 'linux-arm64' : 'linux-x86_64')

    // Match only the .zip asset, not .sha256
    const asset = release.assets?.find(a => a.name === `dev-${assetSuffix}.zip`)
    if (!asset) {
      this.error(`No binary found for ${assetSuffix} in release ${newVersion}`)
    }

    const zipPath = join(tmpdir(), `dev-${newVersion}-${assetSuffix}.zip`)
    const binPath = process.execPath

    try {
      // Download zip
      const dl = await fetch(asset!.browser_download_url, { signal: AbortSignal.timeout(60000) })
      if (!dl.ok) this.error(`Download failed: ${dl.statusText}`)
      await Bun.write(zipPath, dl)

      // Extract binary from zip
      const extractDir = join(tmpdir(), `dev-${newVersion}-${assetSuffix}`)
      const unzip = Bun.spawnSync(['unzip', '-o', zipPath, '-d', extractDir], { stdout: 'pipe', stderr: 'pipe' })
      if (unzip.exitCode !== 0) this.error('Failed to extract downloaded archive')

      const extractedBin = join(extractDir, `dev-${assetSuffix}`)
      if (!existsSync(extractedBin)) this.error(`Expected binary not found after extraction: ${extractedBin}`)

      // Atomic replace
      Bun.spawnSync(['chmod', '+x', extractedBin])
      Bun.spawnSync(['cp', extractedBin, binPath + '.new'])
      Bun.spawnSync(['mv', binPath + '.new', binPath])

      this.log(`${action === 'Upgrading' ? 'Upgraded' : 'Downgraded'} from ${currentVersion} to ${newVersion}.`)
    } finally {
      if (existsSync(zipPath)) unlinkSync(zipPath)
    }
  }

  private compareVersions(a: string, b: string): number {
    const partsA = a.split('.').map(Number)
    const partsB = b.split('.').map(Number)
    for (let i = 0; i < Math.max(partsA.length, partsB.length); i++) {
      const diff = (partsA[i] ?? 0) - (partsB[i] ?? 0)
      if (diff !== 0) return diff
    }
    return 0
  }
}
