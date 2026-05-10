import { Command, Args } from '@oclif/core'
import * as clack from '@clack/prompts'
import { getDevContext } from '../context.js'
import { UserException } from '../exceptions.js'

export default class Open extends Command {
  static id = 'open'
  static description = 'Open a site in the browser'
  static args = {
    site: Args.string({ description: 'Site name', required: false }),
  }

  async run(): Promise<void> {
    const { args } = await this.parse(Open)
    const { dev } = await getDevContext()

    const sites = dev.config.sites()
    const siteNames = Object.keys(sites)

    if (siteNames.length === 0) {
      throw new UserException('No sites found')
    }

    let siteName = args.site
    if (siteName && !(siteName in sites)) {
      this.error(`Site ${siteName} not found. Are you sure you have it configured?`)
      this.exit(1)
    }

    if (!siteName) {
      const selected = await clack.select({
        message: 'Which site will you like to open?',
        options: siteNames.map(name => ({
          value: name,
          label: `${name.charAt(0).toUpperCase() + name.slice(1)} (${sites[name]})`,
        })),
      })
      if (clack.isCancel(selected)) process.exit(0)
      siteName = selected as string
    }

    const url = sites[siteName]
    if (!url) {
      this.error('No site selected')
      this.exit(1)
    }

    this.log(`Opening ${url}`)
    Bun.spawnSync(['open', url])
  }
}
