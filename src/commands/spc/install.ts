import { Command } from '@oclif/core'
import { getDevContext } from '../../context.js'
import { SpcInstallStep } from '../../plugins/spc/steps/spc-install-step.js'

export default class SpcInstall extends Command {
  static id = 'spc:install'
  static description = 'Install latest SPC binary'

  async run(): Promise<void> {
    await this.parse(SpcInstall)
    const { dev } = await getDevContext()
    const step = new SpcInstallStep()
    if (!await dev.runner.execute(step, true)) {
      this.exit(1)
    }
  }
}
