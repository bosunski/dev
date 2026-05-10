import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

const CURRENT_VERSION = '0.0.13'
const RELEASES_URL = 'https://api.github.com/repos/bosunski/dev/releases/latest'

export class CheckUpdateStep extends BaseStep {
  name(): string { return 'Check for DEV updates' }

  async run(runner: Runner): Promise<boolean> {
    try {
      const resp = await fetch(RELEASES_URL, {
        headers: { 'User-Agent': 'dev-cli' },
        signal: AbortSignal.timeout(5000),
      })
      if (!resp.ok) return true
      const data = await resp.json() as { tag_name?: string }
      const latest = data.tag_name?.replace(/^v/, '')
      if (latest && latest !== CURRENT_VERSION) {
        runner.getIO().dev(`New version of DEV is available: ${latest}. Run \`dev upgrade\` to update.`)
      }
    } catch {
      // non-fatal
    }
    return true
  }

  async done(_runner: Runner): Promise<boolean> { return false }

  id(): string { return 'dev.update.check' }
}
