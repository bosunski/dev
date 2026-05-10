import { Command, Args, Flags } from '@oclif/core'

const CURRENT_VERSION = '0.0.13'
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

    if (dryRun) {
      this.warn('Running upgrade in dry-run mode.')
    }

    this.log('Checking for a new version...')

    try {
      const url = args.version
        ? `${RELEASES_API}/tags/${args.version}`
        : `${RELEASES_API}/latest`

      const resp = await fetch(url, {
        headers: { 'User-Agent': 'dev-cli', Accept: 'application/vnd.github+json' },
        signal: AbortSignal.timeout(10000),
      })

      if (!resp.ok) {
        this.error(`Failed to fetch release info: ${resp.statusText}`)
        return
      }

      const release = await resp.json() as { tag_name?: string; assets?: Array<{ name: string; browser_download_url: string }> }
      const newVersion = release.tag_name?.replace(/^v/, '') ?? null

      if (!newVersion) {
        this.log('There are no stable versions available.')
        return
      }

      if (newVersion === CURRENT_VERSION) {
        this.log('You have the latest version installed.')
        return
      }

      const isUpgrade = this.compareVersions(CURRENT_VERSION, newVersion) < 0
      const action = isUpgrade ? 'Upgrading' : 'Downgrading'
      this.log(`${action} from ${CURRENT_VERSION} to ${newVersion}...`)

      if (dryRun) {
        this.log('[dry-run] Would download and replace the current binary.')
        return
      }

      // Find the appropriate asset for this platform
      const platform = process.platform
      const arch = process.arch
      const assetPattern = platform === 'darwin'
        ? (arch === 'arm64' ? 'darwin-arm64' : 'darwin-x86_64')
        : 'linux-x86_64'

      const asset = release.assets?.find(a => a.name.includes(assetPattern))
      if (!asset) {
        this.error(`No binary found for ${assetPattern} in release ${newVersion}`)
        return
      }

      // Download and replace
      const bin = process.execPath
      const resp2 = await fetch(asset.browser_download_url)
      if (!resp2.ok) {
        this.error(`Failed to download: ${resp2.statusText}`)
        return
      }

      const buffer = await resp2.arrayBuffer()
      Bun.write(bin + '.new', buffer)
      Bun.spawnSync(['chmod', '+x', bin + '.new'])
      Bun.spawnSync(['mv', bin + '.new', bin])

      this.log(`${isUpgrade ? 'Upgraded' : 'Downgraded'} from version ${CURRENT_VERSION} to ${newVersion}.`)
    } catch (err) {
      this.error(`Upgrade failed: ${String(err)}`)
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
