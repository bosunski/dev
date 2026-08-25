import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { DnsConfig } from '../config/dns-config.js'

export class DnsStep extends BaseStep {
  constructor(private readonly dns: DnsConfig) { super() }

  name(): string { return `Configure local DNS for ${this.dns.domains().join(', ')}` }
  id(): string {
    const value = `${this.dns.server()}:${this.dns.domains().join(',')}`
    return `dns.${createHash('md5').update(value).digest('hex')}`
  }

  async run(runner: Runner): Promise<boolean> {
    const interfaceName = this.dns.interface()
    for (const command of this.dns.configureCommands(interfaceName)) {
      if (!await runner.exec(command)) return false
    }
    return true
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }
}
